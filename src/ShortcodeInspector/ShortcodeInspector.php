<?php
declare(strict_types=1);

namespace WeCoza\ShortcodeInspector;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;

/**
 * Admin page listing all registered wecoza_* shortcodes with callback source info.
 */
final class ShortcodeInspector
{
    public static function register(): void
    {
        add_action('admin_menu', function () {
            add_management_page(
                'WeCoza Shortcodes',
                'WeCoza Shortcodes',
                'manage_options',
                'wecoza-shortcodes',
                [self::class, 'renderPage']
            );
        });
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        global $shortcode_tags;

        $prefix = 'wecoza_';
        $rows = [];

        foreach ((array) $shortcode_tags as $tag => $callback) {
            if (strpos($tag, $prefix) !== 0) {
                continue;
            }

            [$callable_str, $file, $line] = self::describeCallback($callback);

            $rows[] = [
                'tag'      => $tag,
                'callback' => $callable_str,
                'file'     => $file ?: '—',
                'line'     => $line ?: '—',
            ];
        }

        usort($rows, fn($a, $b) => strcmp($a['tag'], $b['tag']));

        echo '<div class="wrap"><h1>WeCoza Shortcodes</h1>';
        echo '<p>Listing registered shortcodes starting with <code>wecoza_</code>. File/line is best-effort via PHP reflection.</p>';

        if (empty($rows)) {
            echo '<div class="notice notice-info"><p>No matching shortcodes found.</p></div></div>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>
                <th style="width:20%">Shortcode</th>
                <th style="width:35%">Callback</th>
                <th style="width:35%">File</th>
                <th style="width:10%">Line</th>
              </tr></thead><tbody>';

        foreach ($rows as $r) {
            $fileCell = $r['file'];
            if ($fileCell !== '—' && file_exists($fileCell)) {
                $fileCell = esc_html(self::shortenPath($fileCell));
            } else {
                $fileCell = esc_html($r['file']);
            }

            printf(
                '<tr><td><code>%s</code></td><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
                esc_html($r['tag']),
                esc_html($r['callback']),
                $fileCell,
                esc_html((string) $r['line'])
            );
        }

        echo '</tbody></table></div>';
    }

    /**
     * Return [stringRepresentation, file, line] for a callable using reflection.
     */
    public static function describeCallback(mixed $callback): array
    {
        $file = null;
        $line = null;

        try {
            if ($callback instanceof Closure) {
                $ref = new ReflectionFunction($callback);
                $file = $ref->getFileName();
                $line = $ref->getStartLine();
                return ['Closure', $file, $line];
            }

            if (is_string($callback)) {
                if (function_exists($callback)) {
                    $ref = new ReflectionFunction($callback);
                    $file = $ref->getFileName();
                    $line = $ref->getStartLine();
                }
                return [$callback, $file, $line];
            }

            if (is_array($callback) && count($callback) === 2) {
                [$objOrClass, $method] = $callback;

                if (is_object($objOrClass)) {
                    $class = get_class($objOrClass);
                    if (method_exists($objOrClass, $method)) {
                        $ref = new ReflectionMethod($objOrClass, $method);
                        $file = $ref->getFileName();
                        $line = $ref->getStartLine();
                    }
                    return ["{$class}::{$method}", $file, $line];
                }

                if (is_string($objOrClass)) {
                    $class = $objOrClass;
                    if (class_exists($class) && method_exists($class, $method)) {
                        $ref = new ReflectionMethod($class, $method);
                        $file = $ref->getFileName();
                        $line = $ref->getStartLine();
                    }
                    return ["{$class}::{$method}", $file, $line];
                }
            }

            if (is_object($callback) && method_exists($callback, '__invoke')) {
                $class = get_class($callback);
                $ref = new ReflectionMethod($class, '__invoke');
                $file = $ref->getFileName();
                $line = $ref->getStartLine();
                return ["{$class}::__invoke", $file, $line];
            }

            return [self::prettyPrintCallable($callback), $file, $line];
        } catch (Throwable $e) {
            return [self::prettyPrintCallable($callback) . ' (unreflectable)', $file, $line];
        }
    }

    /**
     * Nicely stringify unknown callables without risking notices.
     */
    public static function prettyPrintCallable(mixed $cb): string
    {
        if (is_string($cb)) return $cb;
        if ($cb instanceof Closure) return 'Closure';
        if (is_array($cb)) {
            $a = [];
            foreach ($cb as $part) {
                $a[] = is_object($part) ? get_class($part) : (is_string($part) ? $part : gettype($part));
            }
            return implode('::', $a);
        }
        if (is_object($cb)) return get_class($cb);
        return gettype($cb);
    }

    /**
     * Shorten absolute paths nicely (…/plugins/foo/bar.php).
     */
    public static function shortenPath(string $path): string
    {
        $root = untrailingslashit(ABSPATH);
        $rel = str_replace($root, '', $path);
        $rel = ltrim($rel, '/\\');
        $parts = explode(DIRECTORY_SEPARATOR, $rel);
        if (count($parts) > 4) {
            $rel = '…' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, array_slice($parts, -4));
        }
        return $rel;
    }
}
