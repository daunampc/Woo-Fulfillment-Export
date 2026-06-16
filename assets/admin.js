(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  function formToPayload(form) {
    const data = new FormData(form);
    const payload = {};
    data.forEach(function (value, key) {
      if (key === '_wpnonce' || key === '_wp_http_referer' || key === 'action' || key === 'target_status') {
        return;
      }
      if (key.endsWith('[]')) {
        const clean = key.slice(0, -2);
        if (!payload[clean]) payload[clean] = [];
        payload[clean].push(value);
        return;
      }
      if (payload[key] !== undefined) {
        if (!Array.isArray(payload[key])) payload[key] = [payload[key]];
        payload[key].push(value);
      } else {
        payload[key] = value;
      }
    });

    payload.order_ids = [];
    form.querySelectorAll('.wfe-order-check:checked').forEach(function (checkbox) {
      payload.order_ids.push(checkbox.value);
    });

    return payload;
  }

  function decodeExportUrl(url) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = String(url || '');
    return textarea.value;
  }

  function post(action, data) {
    const body = new FormData();
    body.append('action', action);
    body.append('nonce', WFE_EXPORT.nonce);
    Object.keys(data || {}).forEach(function (key) {
      if (key === 'payload' && typeof data[key] === 'object') {
        const payload = data[key];
        Object.keys(payload).forEach(function (payloadKey) {
          const value = payload[payloadKey];
          if (Array.isArray(value)) {
            value.forEach(function (item) { body.append('payload[' + payloadKey + '][]', item); });
          } else {
            body.append('payload[' + payloadKey + ']', value);
          }
        });
      } else {
        body.append(key, data[key]);
      }
    });

    return fetch(WFE_EXPORT.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: body
    }).then(function (response) {
      return response.json();
    }).then(function (json) {
      if (!json || !json.success) {
        throw new Error(json && json.data && json.data.message ? json.data.message : WFE_EXPORT.errorText);
      }
      return json.data;
    });
  }

  ready(function () {
    const selectAll = document.getElementById('wfe-select-all-orders');
    const count = document.getElementById('wfe-selected-count');
    const checks = Array.from(document.querySelectorAll('.wfe-order-check'));

    function selectedCount() {
      return checks.filter(function (check) { return check.checked; }).length;
    }

    function updateCount() {
      const selected = selectedCount();
      if (count) count.textContent = selected + ' selected';
      if (selectAll) {
        selectAll.checked = checks.length > 0 && selected === checks.length;
        selectAll.indeterminate = selected > 0 && selected < checks.length;
      }
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        checks.forEach(function (check) { check.checked = selectAll.checked; });
        updateCount();
      });
    }
    checks.forEach(function (check) { check.addEventListener('change', updateCount); });
    updateCount();

    const form = document.getElementById('wfe-export-form');
    if (!form || typeof WFE_EXPORT === 'undefined') return;

    const actionInput = document.getElementById('wfe-form-action');
    const targetStatusInput = document.getElementById('wfe-target-status');
    const exportButton = form.querySelector('.wfe-export-button') || form.querySelector('button[type="submit"], .button-primary');
    const progress = document.getElementById('wfe-export-progress');
    const bar = progress ? progress.querySelector('.wfe-progress-bar span') : null;
    const text = progress ? progress.querySelector('.wfe-progress-text') : null;
    let lastSubmitter = null;

    form.querySelectorAll('button[type="submit"]').forEach(function (button) {
      button.addEventListener('click', function () {
        lastSubmitter = button;
      });
    });

    function setProgress(percent, message) {
      if (progress) progress.hidden = false;
      if (bar) bar.style.width = Math.max(0, Math.min(100, percent)) + '%';
      if (text) text.textContent = message;
    }

    form.addEventListener('submit', function (event) {
      const submitter = event.submitter || lastSubmitter;
      const isBulkStatus = submitter && submitter.classList && submitter.classList.contains('wfe-bulk-status-button');

      if (isBulkStatus) {
        if (selectedCount() < 1) {
          event.preventDefault();
          alert(WFE_EXPORT.bulkNoSelectionText || 'Please select at least one order first.');
          return;
        }
        if (actionInput) actionInput.value = 'wfe_bulk_update_orders';
        if (targetStatusInput) targetStatusInput.value = submitter.getAttribute('data-target-status') || 'fulfillment';
        return;
      }

      if (actionInput) actionInput.value = 'wfe_export_orders';
      if (targetStatusInput) targetStatusInput.value = '';

      event.preventDefault();
      const payload = formToPayload(form);
      if (!payload.template_id) {
        alert('Please select a template.');
        return;
      }
      if (exportButton) {
        exportButton.disabled = true;
        exportButton.dataset.originalText = exportButton.textContent;
        exportButton.classList.add('is-busy');
        const exportLabel = exportButton.querySelector('span:last-child');
        if (exportLabel) exportLabel.textContent = WFE_EXPORT.processingText;
        if (!exportLabel) exportButton.textContent = WFE_EXPORT.processingText;
      }
      setProgress(2, WFE_EXPORT.startingText);

      post('wfe_start_export', { payload: payload }).then(function (start) {
        const jobId = start.job_id;
        const total = parseInt(start.total || 0, 10);
        const chunkSize = parseInt(start.chunk_size || 20, 10);
        let offset = 0;

        function processNext() {
          return post('wfe_process_export', { job_id: jobId, offset: offset }).then(function (step) {
            offset = parseInt(step.processed || 0, 10);
            const percent = total > 0 ? Math.round((offset / total) * 90) : 90;
            setProgress(percent, 'Exported ' + offset + ' / ' + total + ' orders...');
            if (step.done) return;
            if (offset <= 0) offset += chunkSize;
            return processNext();
          });
        }

        return processNext().then(function () {
          setProgress(95, 'Creating file...');
          return post('wfe_finish_export', { job_id: jobId });
        });
      }).then(function (finish) {
        setProgress(100, WFE_EXPORT.doneText);
        if (finish.download_url) {
          window.location.href = decodeExportUrl(finish.download_url);
        }
      }).catch(function (error) {
        setProgress(0, error.message || WFE_EXPORT.errorText);
        alert(error.message || WFE_EXPORT.errorText);
      }).finally(function () {
        if (exportButton) {
          exportButton.disabled = false;
          exportButton.classList.remove('is-busy');
          const label = exportButton.querySelector('span:last-child');
          if (label) label.textContent = 'Export orders';
          if (!label) exportButton.textContent = exportButton.dataset.originalText || 'Export selected or filtered orders';
        }
      });
    });
  });
}());
