<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Exception\StickerException;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Our endpoints: a refusal (StickerException) becomes an error in Flarum's shape, with a code the browser turns into a sentence. */
abstract class Endpoint implements RequestHandlerInterface
{
    /** @return array<string, mixed> */
    abstract protected function run(ServerRequestInterface $request, User $actor): array;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return new JsonResponse($this->run($request, RequestUtil::getActor($request)));
        } catch (StickerException $e) {
            return new JsonResponse(['errors' => [['status' => (string) $e->status, 'code' => $e->errorCode]]], $e->status);
        }
    }

    /** @return array<string, mixed> */
    protected function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    protected function limit(User $actor, string $what, int $perMinute, int $perDay): void
    {
        if (! RateLimit::spend('ernestdefoe-sticker-book.rate.'.$what.'.'.$actor->id, $perMinute, $perDay)) {
            throw new StickerException('sticker_slow_down', 429);
        }
    }
}
