<?php

namespace Fabianomendesdev\LaravelJsonResponse;

use Exception;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Throwable;

class JsonResponse
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
    private static $data = [];
    private static $pagination = null;
    private static $set = [];
    private static $relationshipSubgroup = [];
    private static $keyRelationshipSubgroup = null;
    private static $parentKey = null;
    private static $add = null;

    public static function personalizedResponse($codeStatus = 200, $params = [])
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

        if ($data || self::$page || self::$keyResponse == 'list')
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
        return self::$codeStatusResponse == 200 ? true : false;
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

    public static function returnErrorFields($validator) {
        return self::personalizedResponse(400, [
                'message' => "Desculpe, não é possível continuar! Verifique se os campos estão preenchidos corretamente.",
                'errors' => $validator->errors()->messages()]
        );
    }

    public static function systemStandardError(Exception|Throwable $e)
    {
        if (env('APP_DEBUG', false)) {
            return self::personalizedResponse(500, [
                'message' => "Não podemos prosseguir. Ocorreu um erro no sistema.",
                'detail' => [
                    'msg' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]);
        } else {
            return self::personalizedResponse(500, [
                'message' => "Não podemos prosseguir. Ocorreu um erro no sistema."
            ]);
        }
    }

    public static function standardErrorNotFound()
    {
        return self::personalizedResponse(404, [
            'message' => "Não encontramos este caminho."
        ]);
    }

    /**
     * @param Validator $validator
     * @return string
     */
    public static function errorFeedbackMessage(Validator $validator): string
    {
        $message = "";

        $errors = collect($validator->errors()->toArray());

        if (count($errors) > 1)
            $message = 'Erro nos campos '. $errors->keys()->join(',');
        else
            $message = collect($errors->first())->first();

        return $message;
    }
}