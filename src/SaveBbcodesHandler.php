<?php

namespace Olleksi\Bbcodes;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SaveBbcodesHandler implements RequestHandlerInterface
{
    private SettingsRepositoryInterface $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $body = $request->getParsedBody();
        $bbcodes = Arr::get($body, 'bbcodes', []);

        $clean = array_values(array_filter(array_map(function ($item) {
            if (empty($item['name'])) return null;
            return [
                'name'    => substr(trim($item['name']), 0, 32),
                'icon'    => substr(trim($item['icon'] ?? 'fa-star'), 0, 32),
                'tooltip' => substr(trim($item['tooltip'] ?? ''), 0, 64),
                'open'    => substr(trim($item['open'] ?? ''), 0, 128),
                'close'   => substr(trim($item['close'] ?? ''), 0, 128),
                'visible' => (bool)($item['visible'] ?? true),
            ];
        }, $bbcodes)));

        $this->settings->set('olleksi-bbcodes.custom_bbcodes', json_encode($clean));

        return new JsonResponse(['success' => true, 'bbcodes' => $clean]);
    }
}
