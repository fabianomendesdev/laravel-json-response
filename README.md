# laravel-json-response

```shell
composer require fabianomendesdev/laravel-json-response
```

```php
use Fabianomendesdev\Api;
```

```php
return Api::personalizedResponse(200, [
  'message'  => "Sucesso!",
  'response' => new UserResource($user)
]);
```

```php
$validator = Validator::make($request->all(), [
  'nome' => 'required|string|max:100'
]);

if ( $validator->fails() ) {
  return Api::personalizedResponse(300, [
    'message' => Api::errorFeedbackMessage($validator),
    'errors'  => $validator->errors()->messages()
  ]);
}

return Api::successMessage("Success!");
```

```php
$validator = Validator::make($request->all(), [
  'nome' => 'required|string|max:100'
]);

if ($validator->fails()) return Api::returnErrorFields($validator);

return Api::message("Success!", 200);
```

```php	
try {
    if (100 > 200) {
        throw new Exception("Error");
    }
} catch (Exception $e) {
    return Api::systemStandardError(Throwable $e);
}
```

### Exception in Laravel "Exceptions/Handler.php"
```php	
public function register(): void
{
    $this->renderable(function (NotFoundHttpException $notFoundHttpException) {
        return Api::standardErrorNotFound($notFoundHttpException);
    f});
    
    $this->renderable(function (MethodNotAllowedHttpException $methodNotAllowedHttpException) {
        return Api::errorMessage($methodNotAllowedHttpException, "Método não suportado para a rota atual.", 405);
    });
  
    $this->renderable(function (Throwable $exception) {
        return Api::exception($exception);
    });  
}
```