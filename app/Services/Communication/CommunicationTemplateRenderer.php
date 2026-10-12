<?php

namespace App\Services\Communication;

use App\Models\CommunicationTemplate;
use Illuminate\Support\Str;

final class CommunicationTemplateRenderer
{
    /**
     * Render one stored template with its allowlisted variables.
     *
     * @param  array<string, scalar|null>  $variables
     * @return array{subject: string, body: string}
     */
    public function render(CommunicationTemplate $template, array $variables): array
    {
        $variables = array_change_key_case($variables, CASE_LOWER);
        $variables = array_intersect_key($variables, array_flip($template->availableVariables()));

        $subject = $this->replaceVariables($template->subject, $variables);
        $body = $this->replaceVariables($template->body, $variables);

        return [
            'subject' => Str::limit(trim($subject), 150, '…'),
            'body' => trim($body),
        ];
    }

    /**
     * Replace supported double-brace placeholders without evaluating template code.
     *
     * @param  array<string, scalar|null>  $variables
     */
    private function replaceVariables(string $content, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            static function (array $matches) use ($variables): string {
                $key = strtolower((string) $matches[1]);

                return array_key_exists($key, $variables) ? (string) ($variables[$key] ?? '') : (string) $matches[0];
            },
            $content,
        ) ?? $content;
    }
}
