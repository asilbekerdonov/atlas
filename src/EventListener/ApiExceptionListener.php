<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\AttributeInUseException;
use App\Exception\AttributeTypeChangeForbiddenException;
use App\Exception\AttributeValueNotFoundException;
use App\Exception\CvIncompleteException;
use App\Exception\OptimisticLockConflictException;
use App\Exception\PositionAccessDeniedException;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as SecurityAccessDeniedException;

/**
 * Turns every exception raised inside the JSON API (/api/* or Accept: json)
 * into a standardized JSON error body instead of the debug HTML page, so the
 * frontend never breaks on JSON.parse.
 */
#[AsEventListener(event: 'kernel.exception', priority: 10)]
final class ApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $isApiPath = str_starts_with($request->getPathInfo(), '/api/');
        $acceptsJson = str_contains((string) $request->headers->get('Accept', ''), 'application/json');

        if (!$isApiPath && !$acceptsJson) {
            return; // plain HTML flows keep the normal error handling
        }

        $e = $event->getThrowable();
        [$status, $errorCode] = $this->classify($e);

        $event->setResponse(new JsonResponse([
            'success' => false,
            'error' => $errorCode,
            'message' => $e->getMessage(),
            'code' => $status,
        ], $status));
    }

    /** @return array{int, string} [http status, short error code] */
    private function classify(\Throwable $e): array
    {
        return match (true) {
            $e instanceof OptimisticLockConflictException => [Response::HTTP_CONFLICT, 'conflict'],
            $e instanceof AttributeInUseException => [Response::HTTP_CONFLICT, 'attribute_in_use'],
            $e instanceof CvIncompleteException => [Response::HTTP_UNPROCESSABLE_ENTITY, 'incomplete'],
            $e instanceof AttributeValueNotFoundException => [Response::HTTP_NOT_FOUND, 'not_found'],
            $e instanceof AttributeTypeChangeForbiddenException => [Response::HTTP_UNPROCESSABLE_ENTITY, 'type_change_forbidden'],
            $e instanceof PositionAccessDeniedException, $e instanceof SecurityAccessDeniedException => [Response::HTTP_FORBIDDEN, 'access_denied'],
            $e instanceof InvalidArgumentException => [Response::HTTP_BAD_REQUEST, 'bad_request'],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'http_error'],
            default => [Response::HTTP_INTERNAL_SERVER_ERROR, 'internal_error'],
        };
    }
}
