<?php

namespace Fabianomendesdev\LaravelJsonResponse;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Collection as SupCollection;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

interface ApiInterface
{

	public static function personalizedResponse($codeStatus = 200, $params = []): JsonResponse;

	public static function formatErrorMessages(MessageBag $errors): SupCollection;

	public static function returnErrorFields(Validator $validator): JsonResponse;

	public static function systemStandardError(Throwable $e): JsonResponse;

	public static function standardErrorNotFound(NotFoundHttpException $notFoundHttpException): JsonResponse;

	public static function errorFeedbackMessage(Validator $validator): string;

	public static function exception(Throwable $e): JsonResponse;

	public static function message(string $message = "", int $code = 0): JsonResponse;

	public static function errorMessage(Throwable $e, string $message = "", int $code = 0): JsonResponse;

	public static function successMessage(string $message = "", int $code = 200): JsonResponse;
}
