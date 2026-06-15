<?php

defined('ABSPATH') || exit;

final class WFE_Template_Resolver
{
    public static function resolve(string $template, array $data): string
    {
        return WFE_Placeholder_Resolver::resolve($template, $data);
    }
}
