<?php
$path = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/element/cms-element-buy-box.html.twig';
$content = file_get_contents($path);

// Pattern from cat -A: }^M$  \n  })();^M$  </script>^M$
// That's: }\r\n \n })();\r\n </script>
$oldEnd = "}\r\n\n})();\r\n</script>";
$newEnd = "}\r\n</script>";

$newContent = str_replace($oldEnd, $newEnd, $content);

if ($newContent !== $content) {
    file_put_contents($path, $newContent);
    echo "Removed extra })();\n";
} else {
    echo "Pattern not found. Trying alternative patterns...\n";

    // Try just removing the })(); line wherever it appears after the function
    $pattern = '/}\s*\n\s*\)?\s*\)?\s*\(\s*\)?\s*;?\s*\n\s*<\/script>/';
    $replacement = "}\n</script>";
    $newContent = preg_replace($pattern, $replacement, $content);

    if ($newContent !== $content) {
        file_put_contents($path, $newContent);
        echo "Fixed with regex\n";
    } else {
        echo "Could not find pattern. End of file:\n";
        echo bin2hex(substr($content, -100)) . "\n";
    }
}
