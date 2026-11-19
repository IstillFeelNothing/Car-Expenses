<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\ApiExceptionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiExceptionListenerTest extends TestCase
{
    private function dispatch(string $uri, \Throwable $e): ExceptionEvent
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($uri),
            HttpKernelInterface::MAIN_REQUEST,
            $e,
        );
        (new ApiExceptionListener())($event);

        return $event;
    }

    /** @return array<mixed> */
    private function body(ExceptionEvent $event): array
    {
        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    #[DataProvider('nonApiPaths')]
    public function testIgnoresNonApiPaths(string $uri): void
    {
        $event = $this->dispatch($uri, new NotFoundHttpException('x'));

        self::assertNull($event->getResponse());
    }

    /** @return iterable<string, array{string}> */
    public static function nonApiPaths(): iterable
    {
        yield 'site' => ['/nope'];
        yield 'admin' => ['/admin/nope'];
        yield 'login' => ['/login'];
        yield 'root' => ['/'];
        yield 'prefix lookalike' => ['/apiary'];
        yield 'api in the middle' => ['/cars/api/x'];
    }

    public function testNotFoundBecomesJson404(): void
    {
        $event = $this->dispatch('/api/nope', new NotFoundHttpException('No route found for "GET /api/nope"'));

        self::assertSame(404, $event->getResponse()?->getStatusCode());
        self::assertSame(['error' => 'Не знайдено'], $this->body($event));
        self::assertStringStartsWith('application/json', (string) $event->getResponse()?->headers->get('Content-Type'));
    }

    public function testApiRootPathIsHandled(): void
    {
        $event = $this->dispatch('/api/', new NotFoundHttpException());

        self::assertSame(404, $event->getResponse()?->getStatusCode());
    }

    public function testMethodNotAllowedKeepsAllowHeader(): void
    {
        $event = $this->dispatch('/api/cars', new MethodNotAllowedHttpException(['GET'], 'Method not allowed'));

        self::assertSame(405, $event->getResponse()?->getStatusCode());
        self::assertSame(['error' => 'Метод не дозволений'], $this->body($event));
        self::assertSame('GET', $event->getResponse()?->headers->get('Allow'));
    }

    public function testGenericHttpExceptionKeepsStatusAndHeaders(): void
    {
        $event = $this->dispatch('/api/cars', new HttpException(429, 'secret detail', null, ['Retry-After' => '10']));

        self::assertSame(429, $event->getResponse()?->getStatusCode());
        self::assertSame('10', $event->getResponse()?->headers->get('Retry-After'));
        self::assertStringNotContainsString('secret', (string) $event->getResponse()?->getContent());
    }

    public function testAccessDeniedHttpException(): void
    {
        $event = $this->dispatch('/api/cars', new AccessDeniedHttpException('hidden'));

        self::assertSame(403, $event->getResponse()?->getStatusCode());
        self::assertStringNotContainsString('hidden', (string) $event->getResponse()?->getContent());
    }

    public function testUnexpectedThrowableBecomes500WithoutLeakingDetails(): void
    {
        $event = $this->dispatch('/api/cars', new \RuntimeException('SQLSTATE[HY000]: password=hunter2'));

        self::assertSame(500, $event->getResponse()?->getStatusCode());
        self::assertSame(['error' => 'Внутрішня помилка сервера'], $this->body($event));
        $content = (string) $event->getResponse()?->getContent();
        self::assertStringNotContainsString('SQLSTATE', $content);
        self::assertStringNotContainsString('hunter2', $content);
    }

    public function testServerHttpExceptionDoesNotLeakMessage(): void
    {
        $event = $this->dispatch('/api/cars', new HttpException(503, 'db down at 10.0.0.5'));

        self::assertSame(503, $event->getResponse()?->getStatusCode());
        self::assertStringNotContainsString('10.0.0.5', (string) $event->getResponse()?->getContent());
    }
}
