<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;

class EnsureCorrectDataType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Check if the response is a JSON response
        if ($response instanceof JsonResponse) {
            $data = $response->getData();

            // Fix data types in the response data
            $data = $this->fixDataTypes($data);

            // Set the corrected data back to the response
            $response->setData($data);
        }

        return $response;
    }

    /**
     * Fix data types in the given data.
     *
     * @param  mixed  $data
     * @return mixed
     */
    protected function fixDataTypes($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value) && $this->isJsonArray($value)) {
                    $data[$key] = json_decode($value, true);
                } elseif (is_array($value) || is_object($value)) {
                    $data[$key] = $this->fixDataTypes($value);
                }
            }
        } elseif (is_object($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value) && $this->isJsonArray($value)) {
                    $data->{$key} = json_decode($value, true);
                } elseif (is_array($value) || is_object($value)) {
                    $data->{$key} = $this->fixDataTypes($value);
                }
            }
        }

        return $data;
    }

    /**
     * Check if a string is a JSON array.
     *
     * @param  string  $string
     * @return bool
     */
    protected function isJsonArray($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE && is_array(json_decode($string, true)));
    }
}