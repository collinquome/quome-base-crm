<?php

namespace Webkul\Admin\Exceptions;

use App\Exceptions\Handler as AppExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends AppExceptionHandler
{
    /**
     * Json error messages.
     *
     * @var array
     */
    protected $jsonErrorMessages = [];

    /**
     * Create handler instance.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->jsonErrorMessages = [
            '404' => trans('admin::app.common.resource-not-found'),
            '403' => trans('admin::app.common.forbidden-error'),
            '401' => trans('admin::app.common.unauthenticated'),
            '500' => trans('admin::app.common.internal-server-error'),
        ];
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        // Route AuthenticationException through unauthenticated() even when
        // app.debug is off — otherwise the renderCustomResponse() branch below
        // treats it as a generic Throwable and returns 500 instead of 401.
        if ($exception instanceof AuthenticationException) {
            return $this->unauthenticated($request, $exception);
        }

        if (! config('app.debug')) {
            return $this->renderCustomResponse($exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->isJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->jsonErrorMessages[401]], 401);
        }

        // Admin is the only authenticated surface in this app; the original
        // `customer.session.index` route was removed along with the shop.
        if (\Illuminate\Support\Facades\Route::has('admin.login.index')) {
            return redirect()->guest(route('admin.login.index'));
        }

        return redirect()->guest('/admin/login');
    }

    /**
     * Render custom HTTP response.
     *
     * @return \Illuminate\Http\Response|null
     */
    private function renderCustomResponse(Throwable $exception)
    {
        if ($exception instanceof HttpException) {
            $statusCode = in_array($exception->getStatusCode(), [401, 403, 404, 503])
                ? $exception->getStatusCode()
                : 500;

            return $this->response($statusCode);
        }

        if ($exception instanceof ValidationException) {
            return parent::render(request(), $exception);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->response(404);
        } elseif ($exception instanceof PDOException || $exception instanceof \ParseError) {
            return $this->response(500);
        } else {
            return $this->response(500);
        }
    }

    /**
     * Return custom response.
     *
     * @param  string  $path
     * @param  string  $errorCode
     * @return mixed
     */
    private function response($errorCode)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => isset($this->jsonErrorMessages[$errorCode])
                    ? $this->jsonErrorMessages[$errorCode]
                    : trans('admin::app.common.something-went-wrong'),
            ], $errorCode);
        }

        return response()->view('admin::errors.index', compact('errorCode'));
    }
}
