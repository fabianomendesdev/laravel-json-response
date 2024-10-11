<?php

namespace Fabianomendesdev\LaravelJsonResponse;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection as SupCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class Api implements ApiInterface
{
	const PARAM_RESPONSE = ['id', 'created_at', 'updated_at'];
	private static $response = null;
	private static $keyResponse = 'data';
	private static $codeStatusResponse = 200;
	private static $message = null;
	private static $errors = [];
	private static $detail = null;
	private static $subgroup = null;
	private static $page = null;
	private static $data = null;
	private static $pagination = null;
	private static $set = null;
	private static $relationshipSubgroup = null;
	private static $keyRelationshipSubgroup = null;
	private static $parentKey = null;
	private static $add = null;

	public static function personalizedResponse($codeStatus = 200, $params = []): JsonResponse
	{
		self::$codeStatusResponse = $codeStatus;
		self::paramsProcessor($params);

		if (self::$response instanceof Paginator) {
			self::$data = self::buildData(self::$response->items());
			if (!self::$pagination)
				self::$pagination = self::$response;
		} else if (self::$response instanceof Model || is_array(self::$response)) {
			self::$data = self::buildData(self::$response);
		} else if (self::$response instanceof Collection) {
			self::$data = self::buildData(self::$response->toArray());
		} else if (self::$response instanceof SupCollection) {
			self::$data = self::buildData(self::$response);
		} else if (self::$response instanceof ResourceCollection) {
			self::$data = self::buildData(self::$response->jsonSerialize());
			if (!self::$pagination)
				self::$pagination = self::$response->resource;
		} else if (self::$response instanceof JsonResource) {
			self::$data = self::buildData(collect(self::$response->jsonSerialize()));
		}

		return response()->json(
			self::buildResult() ?? [],
			self::$codeStatusResponse,
			[],
			JSON_UNESCAPED_SLASHES
		);
	}

	private static function paramsProcessor(Array $params)
	{
		self::$response = Arr::exists($params, 'response') ? $params['response'] : null;
		self::$pagination = Arr::exists($params, 'pagination') ? $params['pagination'] : null;
		self::$page = Arr::exists($params, 'page') ? $params['page'] : null;
		self::$message = Arr::exists($params, 'message') ? $params['message'] : '';
		self::$errors = Arr::exists($params, 'errors') ? $params['errors'] : [];
		self::$detail = Arr::exists($params, 'detail') ? $params['detail'] : '';
		self::$subgroup = Arr::exists($params, 'subgroup') ? $params['subgroup'] : null;
		self::$set = Arr::exists($params, 'set') ? $params['set'] : null;
		self::$relationshipSubgroup = Arr::exists($params, 'relationshipSubgroup') ? $params['relationshipSubgroup'] : null;
		self::$keyRelationshipSubgroup = Arr::exists($params, 'keyRelationshipSubgroup') ? $params['keyRelationshipSubgroup'] : null;
		self::$parentKey = Arr::exists($params, 'parentKey') ? $params['parentKey'] : null;
		self::$add = Arr::exists($params, 'add') ? $params['add'] : null;
	}

	private static function buildResult()
	{
		$data = self::$data;
		if (!self::$page) self::buildPageForPagination();

		$result = [
			"success" => self::getSuccessResult()
		];

		if (self::$message)
			$result = Arr::add($result, 'message', self::$message);

		if (self::$detail)
			$result = Arr::add($result, 'detail', self::$detail);

		if (self::$set)
			$data = array_merge($data, self::$set);

		if (self::$parentKey)
			self::$keyResponse = self::$parentKey;

		if (($data || self::$page || self::$keyResponse == 'list') && self::getSuccessResult())
			$result = Arr::add($result, self::$keyResponse, $data ?? []);

		if (self::$add) {
			foreach (self::$add as $index => $item) {
				$result = Arr::add($result, $index, $item ?? []);
			}
		}

		if (count(self::$errors) > 0)
			$result = Arr::add($result, 'errors', (Object) self::getFormattedErrors());

		if (self::$page)
			$result = Arr::add($result, 'page', self::$page);

		return $result;
	}

	private static function getSuccessResult()
	{
		return match (self::$codeStatusResponse) {
			100, 101, 102, 200, 201, 202, 203, 204, 205, 206, 207, 208, 226 => true,
			default => false,
		};
	}

	private static function buildPageForPagination()
	{
		$pagination = self::$pagination;

		if ($pagination instanceof Paginator) {
			self::$keyResponse = 'list';
			self::$page = [
				"total" => $pagination->total(),
				"page_limit" => $pagination->perPage(),
				"page" => $pagination->currentPage(),
				"total_pages" => $pagination->hasPages() ? ceil($pagination->total() / $pagination->perPage()) : 1
			];
		} else {
			return false;
		}

		return true;
	}

	private static function getFormattedErrors()
	{
		$errors = [];

		foreach (self::$errors as $key => $value) {
			$errors = Arr::add($errors, $key, is_array($value) ? Arr::first($value) : $value);
		}

		return $errors;
	}

	/**
	 * @param MessageBag $errors
	 * @return SupCollection
	 */
	public static function formatErrorMessages(MessageBag $errors): SupCollection
	{
		return collect($errors->toArray())->map(function ( $item ) {
			return collect($item)->first();
		});
	}

	private static function buildData($response)
	{
		$data = [];

		if (is_array($response)) {
			self::$keyResponse = 'list';
			foreach ($response as $item) {
				if ($item instanceof Model) {
					$data[] = self::organizeResult($item->toArray());
				} else if (is_array($item)) {
					$data[] = self::organizeResult($item);
				}
			}
		} else if (is_object($response)) {
			self::$keyResponse = 'data';
			$data = self::organizeResult($response->toArray());
		}

		return $data;
	}

	private static function organizeResult($arrayValue)
	{
		$temp = Arr::only($arrayValue, self::PARAM_RESPONSE);
		$temp = array_merge($temp, Arr::except($arrayValue, self::PARAM_RESPONSE));

		if (self::$relationshipSubgroup && self::$keyRelationshipSubgroup) {
			$keyFirst = array_key_first(self::$relationshipSubgroup);

			if (($arrayValue[self::$keyRelationshipSubgroup] ?? false) && $keyFirst) {
				$valueFirst = self::$relationshipSubgroup[$keyFirst];
				$index = $arrayValue[self::$keyRelationshipSubgroup];
				$temp = array_merge($temp, [$keyFirst => is_array($valueFirst[$index]) ? $valueFirst[$index] : array($valueFirst[$index])]);
			}
		}

		if (self::$subgroup) {
			foreach (self::$subgroup as $index => $item) {
				$temp = array_merge($temp, [$index => $item]);
			}
		}

		return $temp;
	}

	/**
	 * @param Validator $validator
	 * @return \Illuminate\Http\JsonResponse
	 */
	public static function returnErrorFields(Validator $validator): JsonResponse
	{
		return self::personalizedResponse(400, [
				'message' => "Desculpe, não é possível continuar! Verifique se os campos estão preenchidos corretamente.",
				'errors'  => $validator->errors()->messages()]
		);
	}

	/**
	 * @param Throwable $e
	 * @return JsonResponse
	 */
	public static function systemStandardError(Throwable $e): JsonResponse
	{
		return self::error($e, self::getStatusCode($e), "Não podemos prosseguir. Ocorreu um erro no sistema.");
	}

	public static function standardErrorNotFound(NotFoundHttpException $notFoundHttpException): JsonResponse
	{
		return self::error($notFoundHttpException, 404, "Não encontramos este caminho.");
	}

	/**
	 * @param Validator $validator
	 * @return string
	 */
	public static function errorFeedbackMessage(Validator $validator): string
	{
		$message = "";

		$errors = collect($validator->errors()->toArray());

		if ($errors->count() > 1) {
			$message = 'Erro nos campos ';

			foreach ($errors->keys() as $i => $key) {
				if ( $i == ($errors->keys()->count() - 1) ) $message .= " e $key.";
				else if ( $i > 0 ) $message .= ", $key";
				else $message .= "$key";
			}
		} else if ( $errors->count() > 0 ) {
			$message = collect($errors->first())->first();
		}

		return $message;
	}

	/**
	 * @param Throwable $e
	 * @return JsonResponse
	 */
	public static function exception(Throwable $e): JsonResponse
	{
		if (self::isDebug())
			return self::error($e, self::getStatusCode($e), $e->getMessage());

		return self::systemStandardError($e);
	}

	/**
	 * @param Throwable $e
	 * @param int $code
	 * @param string $msm
	 * @return JsonResponse
	 */
	private static function error(Throwable $e, int $code = 0, string $msm = ""): JsonResponse
	{
		return self::personalizedResponse($code ?: 500, [
			'message' => self::isDebug() && $e->getMessage() ? $e->getMessage() : $msm,
			'detail'  => self::errorDetail($e)
		]);
	}

	/**
	 * @param string $message
	 * @param int $code
	 * @return JsonResponse
	 */
	public static function message(string $message = "", int $code = 0): JsonResponse
	{
		return self::personalizedResponse($code ?: 400, [
			'message' => $message
		]);
	}

	/**
	 * @param Throwable $e
	 * @param string $message
	 * @param int $code
	 * @return JsonResponse
	 */
	public static function errorMessage(Throwable $e, string $message = "", int $code = 0): JsonResponse
	{
		return self::error($e, self::getStatusCode($e, $code), $message);
	}

	/**
	 * @param Throwable $e
	 * @param string $message
	 * @param int $code
	 * @return JsonResponse
	 */
	public static function successMessage(string $message = "", int $code = 200): JsonResponse
	{
		return self::success($code, $message);
	}

	/**
	 * @param Throwable $e
	 * @param int $code
	 * @param string $msm
	 * @return JsonResponse
	 */
	private static function success(int $code = 200, string $msm = ""): JsonResponse
	{
		return self::personalizedResponse($code ?: 200, [
			'message' => $msm
		]);
	}

	/**
	 * @param Throwable $e
	 * @param int $code
	 * @return int
	 */
	private static function getStatusCode(Throwable $e, int $code = 0): int
	{
		if (! $code and $e instanceof HttpExceptionInterface)
			$code = $e->getStatusCode();

		return $code ?: 500;
	}

	/**
	 * @param Throwable $e
	 * @return array|null
	 */
	private static function errorDetail(Throwable $e): array | null
	{
		if (self::isDebug()) {
			return [
				'msg' => $e->getMessage(),
				'code' => $e->getCode(),
				'file' => $e->getFile(),
				'line' => $e->getLine()
			];
		}

		return null;
	}

	/**
	 * @return bool
	 */
	private static function isDebug(): bool
	{
		return env('APP_DEBUG', false) && env('LOG_LEVEL', 'production') === 'debug';
	}
}
