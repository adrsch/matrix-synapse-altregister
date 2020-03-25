<?php declare(strict_types=1);
namespace Uotc;

global $errors;
$errors = Array();

function handleErrors( int $errno, string $errstr, string $errfile, int $errline, array $errcontext ): bool {
	global $errors;
    $errors[] = $errstr;

    // testing only
    printf( "\n%d\n %s\n %d\n %s\n", $errno, $errfile, $errline, $errstr );
    print_r( $errcontext );

    return true;
}

function renderHtml( callable $renderPage ): void {
    echo '<!doctype html>';
    echo '<html lang="en">';
    $renderPage();
    echo '</html>';
}

function renderHead( callable $renderAdditional = null ): void {
    echo '<head>';
    require dirname(__DIR__) . '/include/head.inc';
    if ($renderAdditional) { $renderAdditional(); }
    echo '</head>';
}

function renderNav(): void { require dirname(__DIR__) . '/include/nav.inc'; }

function renderErrors(): void { require dirname(__DIR__) . '/include/errors.inc'; }

function renderBody( callable $renderContent ): void {
    echo '<body>';
    $renderContent();
    echo '</body>';
}

function renderSuccess( string $text ): void {
    echo '<div class="success" style="display: flex; align-items: center;justify-content: center;">';
    echo '<h2>';
    echo $text;
    echo '</h2>';
    echo '</div>';
}
