<?php
/**
 * Fix the selectSnigelColor function scope issue
 * The function was incorrectly placed inside an IIFE - move it to global scope
 */

$path = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig';
$content = file_get_contents($path);

// Find the pattern: the IIFE is missing its closing })(); before our function
// Handle CRLF (Windows) line endings
$oldPattern = "initReviewForm();\r\n        }\r\n    \r\n// Snigel color selector function";
$newPattern = "initReviewForm();\r\n        }\r\n    })();\r\n\r\n// ========== SNIGEL COLOR SELECTOR (GLOBAL FUNCTION) ==========";

if (strpos($content, '})();' . "\r\n\r\n// ========== SNIGEL COLOR SELECTOR (GLOBAL FUNCTION)") !== false) {
    echo "Already fixed!\n";
    exit(0);
}

$newContent = str_replace($oldPattern, $newPattern, $content);

if ($newContent !== $content) {
    // Also need to remove the extra })(); at the end that was closing the now-closed IIFE
    $newContent = str_replace("}\r\n\r\n})();\r\n</script>", "}\r\n</script>", $newContent);

    file_put_contents($path, $newContent);
    echo "Fixed! Function is now in global scope.\n";
} else {
    echo "Pattern not found. Checking current state...\n";
    if (strpos($content, 'function selectSnigelColor') === false) {
        echo "Function doesn't exist at all!\n";
    } else {
        echo "Function exists but pattern differs.\n";
        // Show context
        $pos = strpos($content, 'function selectSnigelColor');
        echo "Context: " . substr($content, max(0, $pos - 100), 300) . "\n";
    }
}
