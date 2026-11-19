<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Єдиний JSON-формат помилок для /api/*: {"error": "<повідомлення>"}.
 * Текст береться з таблиці за статусом, а не з виключення, тож деталі (SQL, шляхи) не витікають.
 * Заголовки HttpException (наприклад, Allow для 405) зберігаються. Не-/api шляхи не чіпає.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ApiExceptionListener
{
    private const MESSAGES = [
        400 => 'Некоректний запит',
        401 => 'Потрібна автентифікація',
        403 => 'Доступ заборонено',
        404 => 'Не знайдено',
        405 => 'Метод не дозволений',
        406 => 'Неприйнятний формат відповіді',
        415 => 'Непідтримуваний тип вмісту',
        429 => 'Забагато запитів',
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if ('/api' !== $path && !str_starts_with($path, '/api/')) {
            return;
        }

        $e = $event->getThrowable();
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = [];
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $headers = $e->getHeaders();
        }

        $message = self::MESSAGES[$status] ?? ($status >= 500 ? 'Внутрішня помилка сервера' : 'Помилка запиту');

        $event->setResponse(new JsonResponse(['error' => $message], $status, $headers));
    }
}
