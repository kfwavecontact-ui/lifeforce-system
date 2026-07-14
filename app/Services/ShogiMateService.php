<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ShogiMateService
{
    private const PIECES = [
        'king', 'rook', 'bishop', 'gold', 'silver', 'knight', 'lance', 'pawn',
        'dragon', 'horse', 'promotedSilver', 'promotedKnight', 'promotedLance', 'tokin',
    ];

    private const HAND_PIECES = ['rook', 'bishop', 'gold', 'silver', 'knight', 'lance', 'pawn'];

    public function normalizeSettings(array $settings): array
    {
        $components = data_get($settings, 'components', []);
        if (! is_array($components)) {
            return $settings;
        }

        foreach ($components as $index => $component) {
            if (! is_array($component) || ($component['type'] ?? null) !== 'shogi_mate') {
                continue;
            }

            $components[$index]['shogi_mate'] = $this->normalizeConfig(
                is_array($component['shogi_mate'] ?? null) ? $component['shogi_mate'] : []
            );
        }

        data_set($settings, 'components', array_values($components));

        return $settings;
    }

    public function normalizeAndValidateSettings(array $settings, string $attributePrefix = 'steps'): array
    {
        $settings = $this->normalizeSettings($settings);
        $components = data_get($settings, 'components', []);
        $errors = [];

        foreach ($components as $index => $component) {
            if (($component['type'] ?? null) !== 'shogi_mate') {
                continue;
            }

            $config = $component['shogi_mate'] ?? [];
            $prefix = $attributePrefix . '.settings.components.' . $index . '.shogi_mate';

            $attackerSide = ($config['turn'] ?? 'black') === 'white' ? 'white' : 'black';
            $defenderSide = $attackerSide === 'black' ? 'white' : 'black';

            // 詰将棋では攻め方の玉を省略した局面も正規の問題として扱う。
            // 詰み判定に必要な守備側の玉だけを必須とする。
            if (! $this->hasKing($config['board'], $defenderSide)) {
                $errors[$prefix . '.board'][] = '詰ませる側の玉を盤面に配置してください。';
            }
            $solutionRoutes = is_array($config['solution_routes'] ?? null)
                ? array_values(array_filter($config['solution_routes'], 'is_array'))
                : [];

            if ($solutionRoutes === [] || collect($solutionRoutes)->every(fn ($route) => count($route) < 1)) {
                $errors[$prefix . '.solution_routes'][] = '正解手順を1手以上登録してください。';
            }

            foreach ($solutionRoutes as $routeIndex => $route) {
                if (count($route) < 1) {
                    $errors[$prefix . '.solution_routes.' . $routeIndex][] = '正解ルートに手順を登録してください。';
                    continue;
                }

                if ((int) $config['mate_in'] !== count($route)) {
                    $errors[$prefix . '.solution_routes.' . $routeIndex][] = '手数と正解ルートの登録数が一致していません。';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $settings;
    }

    public function normalizeConfig(array $raw): array
    {
        $board = $this->normalizeBoard($raw['board'] ?? null);
        $hands = $this->normalizeHands($raw['hands'] ?? null);
        $legacyMoves = is_array($raw['solution_moves'] ?? null)
            ? array_values(array_filter($raw['solution_moves'], 'is_array'))
            : [];
        $rawRoutes = is_array($raw['solution_routes'] ?? null)
            ? array_values(array_filter($raw['solution_routes'], 'is_array'))
            : [];
        $routes = $rawRoutes !== []
            ? array_map(
                static fn ($route) => array_values(array_filter($route, 'is_array')),
                $rawRoutes
            )
            : [$legacyMoves];
        if ($routes === []) {
            $routes = [[]];
        }
        $moves = $routes[0] ?? [];
        $legacyHint = trim((string) ($raw['hint'] ?? ''));
        $hintLevels = is_array($raw['hint_levels'] ?? null) ? $raw['hint_levels'] : [$legacyHint, '', ''];
        $hintLevels = array_map(static fn ($value) => (string) $value, array_slice(array_pad($hintLevels, 3, ''), 0, 3));

        return [
            'version' => 1,
            'title' => trim((string) ($raw['title'] ?? '詰将棋に挑戦')) ?: '詰将棋に挑戦',
            'prompt' => trim((string) ($raw['prompt'] ?? '相手の玉を詰ませましょう。')) ?: '相手の玉を詰ませましょう。',
            'board' => $board,
            'hands' => $hands,
            'turn' => ($raw['turn'] ?? 'black') === 'white' ? 'white' : 'black',
            'mate_in' => max(1, (int) ($raw['mate_in'] ?? count($moves) ?: 1)),
            'solution_moves' => $moves,
            'solution_routes' => $routes,
            'hint' => collect($hintLevels)->first(fn ($value) => trim($value) !== '') ?? $legacyHint,
            'hint_levels' => $hintLevels,
            'explanation' => (string) ($raw['explanation'] ?? ''),
            'settings' => array_merge([
                'allow_retry' => true,
                'show_hint' => true,
                'show_answer' => false,
                'require_correct' => true,
                'flipped' => false,
                'show_coordinates' => true,
            ], is_array($raw['settings'] ?? null) ? $raw['settings'] : []),
            'scoring' => array_merge([
                'target_time_ms' => max(15000, max(1, (int) ($raw['mate_in'] ?? 1)) * 30000),
                'retry_penalty' => 12,
                'hint_penalty' => 18,
                'undo_penalty' => 5,
                'restart_penalty' => 8,
            ], is_array($raw['scoring'] ?? null) ? $raw['scoring'] : []),
        ];
    }

    private function normalizeBoard(mixed $raw): array
    {
        $board = [];
        for ($row = 0; $row < 9; $row++) {
            $line = [];
            for ($column = 0; $column < 9; $column++) {
                $piece = is_array($raw) && is_array($raw[$row] ?? null) ? ($raw[$row][$column] ?? null) : null;
                if (! is_array($piece) || ! in_array($piece['type'] ?? null, self::PIECES, true)) {
                    $line[] = null;
                    continue;
                }
                $line[] = [
                    'type' => $piece['type'],
                    'side' => ($piece['side'] ?? 'black') === 'white' ? 'white' : 'black',
                ];
            }
            $board[] = $line;
        }
        return $board;
    }

    private function normalizeHands(mixed $raw): array
    {
        $hands = ['black' => [], 'white' => []];
        foreach (['black', 'white'] as $side) {
            foreach (self::HAND_PIECES as $piece) {
                $hands[$side][$piece] = max(0, (int) data_get($raw, "$side.$piece", 0));
            }
        }
        return $hands;
    }

    private function hasKing(array $board, string $side): bool
    {
        foreach ($board as $row) {
            foreach ($row as $piece) {
                if (($piece['type'] ?? null) === 'king' && ($piece['side'] ?? null) === $side) {
                    return true;
                }
            }
        }
        return false;
    }
}
