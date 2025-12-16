<?php
/**
 * Fix register page logo - move outside wrapper and position at top
 */

$file = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/account/register/index.html.twig';
$content = file_get_contents($file);

// Move logo outside auth-wrapper - put it right after back-home-btn
$old = '    <div class="auth-wrapper{% if isRegisterPage %} register-mode{% endif %}">
        {# Logo Section #}
        <div class="auth-logo">
            <a href="{{ path(\'frontend.home.page\') }}">
                <div class="logo-image" style="background-image:url(\'{{ asset(\'bundles/raventheme/assets/raven-logo.png\') }}\');background-size:contain;background-position:center;background-repeat:no-repeat;height:220px;width:750px;"></div>
            </a>
        </div>

        {% if isRegisterPage %}';

$new = '    {# Logo Section - positioned at top, centered #}
    <div class="auth-logo">
        <a href="{{ path(\'frontend.home.page\') }}">
            <div class="logo-image" style="background-image:url(\'{{ asset(\'bundles/raventheme/assets/raven-logo.png\') }}\');background-size:contain;background-position:center;background-repeat:no-repeat;height:80px;width:280px;"></div>
        </a>
    </div>

    <div class="auth-wrapper{% if isRegisterPage %} register-mode{% endif %}">
        {% if isRegisterPage %}';

$content = str_replace($old, $new, $content);

// Remove padding-top from auth-wrapper since logo is now outside
$content = str_replace('padding-top: 120px;', '', $content);

file_put_contents($file, $content);
echo "Logo moved outside wrapper and positioned at top\n";
