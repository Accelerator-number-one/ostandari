<?php

namespace App\Services;

class Html
{
    public static function articleBody(string $html): string
    {
        return LegacyLinkPages::html(preg_replace('~<p(?:\\s[^>]*)?>(?:\\s|\\x{00a0}|&nbsp;|<br\\s*/?>)*</p>~ui', '', self::clean($html)));
    }

    public static function clean(string $html): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', 'p[class],div[class],br,strong,b,em,i,u,ul,ol,li,blockquote,h2,h3,h4,a[href|title],img[src|alt|title|width|height],table,caption,thead,tbody,tfoot,tr,th[colspan|rowspan|scope],td[colspan|rowspan]');
        $config->set('Attr.AllowedClasses', ['portal-image-album', 'portal-image-album-item', 'portal-image-album-caption', 'portal-image-album-columns-1', 'portal-image-album-columns-2', 'portal-image-album-columns-3']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

        return (new \HTMLPurifier($config))->purify($html);
    }
}
