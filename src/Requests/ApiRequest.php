<?php

namespace Izupet\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Izupet\Api\Traits\ApiResponse;
use Illuminate\Validation\ValidationException;
use Config;

class ApiRequest extends FormRequest
{
    use ApiResponse;

    /**
     * Array of rules set during init. Default 'suppressResponseHttpStatusCode' is
     * always present. This param is always allowed to be part of query string and it
     * represents reponse HTTP status code. If params is present in query string response
     * HTTP status code will always be 200 regardless error occured.
     *
     * @return array
     */
    protected $rules = [
        'suppressResponseHttpStatusCode' => ''
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Validate the input.
     *
     * @param  \Illuminate\Validation\Factory $factory
     * @return \Illuminate\Validation\Validator
     */
    public function validator($factory)
    {
        $factory->extend('fields', function($attribute, $value, $parameters) {
            $parameters = unserialize($parameters[0]);

            foreach ($this->fields as $key => $value) {
                if (is_array($value)) {
                    if (!array_key_exists($key, $parameters)) {
                        return false;
                        break;
                    }

                    foreach ($value as $k => $v) {
                      if (!in_array($v, $parameters[$key])) {
                          return false;
                          break;
                      }
                    }
                } else {
                    if (!in_array($value, $parameters)) {
                        return false;
                        break;
                    }
                }
            }

            return true;
        }, 'Some fields are not applicable.');

        $factory->extend('order', function($attribute, $value, $parameters) {
            $parameters = unserialize($parameters[0]);

            $order = explode('.', $value);

            if (isset($order[1]) && !in_array(strtolower($order[1]), ['asc', 'desc'])) {
                return false;
            }

            if (count($order) !== 2) {
                return false;
            }

            if (!in_array($order[0], $parameters)) {
                return false;
            }

            return true;
        }, 'Order by is not applicable.');

        $this->container->call([$this, strtolower($this->method()) . 'Rules']);

        if (method_exists($this, 'customValidator')) {
            $this->customValidator($factory);
        }

        $validator = $factory->make(
            $this->sanitize(),
            $this->rules,
            $this->messages()
        );

        if ($validator->passes()) {
            $validator->after(function () {
                $this->arrange();
                $this->replace($this->convertToSnakeCase($this->all()));
                !method_exists($this, 'modify') ?: $this->replace($this->modify($this->all()));
            });
        }

        return $validator;
    }

    /**
     * Convert fields to snake case after validation.
     *
     * @param array $input
     * @return array
     */
    public function convertToSnakeCase(array $input)
    {
        foreach ($input as $key => $value)
        {
            if (is_array($value)) {
                $input[snake_case($key)] = $this->convertToSnakeCase($value);
            } else {
              unset($input[$key]);
              $input[snake_case($key)] = is_string($value) ? snake_case($value) : $value;
            }

        }

        return $input;
    }

    /**
     * Sanitize input before validation. If any unsupported field is present an
     * exception is thrown.
     *
     * @return array
     */
    protected function sanitize()
    {
        if ($invalidParams = array_diff_key($this->all(), $this->rules)) {
            throw new ValidationException(null, $this->response([
              key($invalidParams) => ["Invalid param " . key($invalidParams) . "."]
            ]));
        }

        return $this->all();
    }

    /**
     * Set rules for limit and offset. If limit and offset params not present and
     * set in query string, use default values from config.
     *
     * @return ApiRequest
     */
    protected function pagination()
    {
        $this->checkAllowedMethod(['get']);

        $input = $this->all();

        if (!array_key_exists('offset', $input)) {
            $input['offset'] = Config::get('api.offset');
        }

        if (!array_key_exists('limit', $input)) {
            $input['limit'] = Config::get('api.limit');
        }

        $this->replace($input);

        $this->rules = array_merge($this->rules, [
            'limit'   => 'numeric|min:1',
            'offset'  => 'numeric|min:0'
        ]);

        return $this;
    }

    /**
     * Set rules for search.
     *
     * @return ApiRequest
     */
    protected function search(array $fields)
    {
        $this->checkAllowedMethod(['get']);

        $input = $this->all();

        if (!array_key_exists('q', $input)) {
            $input['q'] = "";
        }

        $this->replace($input);

        $this->rules = array_merge($this->rules, [
            'q'   => 'alpha'
        ]);

        $this->searchFields = $fields;

        return $this;
    }

    /**
     * Set rules for fields.
     *
     * @return ApiRequest
     */
    public function fields(array $fields)
    {
        $this->checkAllowedMethod(['put', 'post', 'get']);

        $input = $this->all();

        if (array_key_exists('fields', $input)) {
            $input['fields'] = $this->parseFieldsToArray();
        } else {
            $input['fields'] = $fields;
        }

        $this->replace($input);

        $this->rules = array_merge($this->rules, [
            'fields' => 'fields:' . serialize($fields)
        ]);

        return $this;
    }

    /**
     * Parse string fields value to array.
     *
     * @return array
     */
    private function parseFieldsToArray()
    {
        $fields = explode(',', $this->fields);
        foreach ($fields as $key => $field) {
            $fieldParts = explode('.', $field);

            if (count($fieldParts) === 2) {
                $fields[$fieldParts[0]][] = $fieldParts[1];
                unset($fields[$key]);
            }
        }

        return $fields;
    }

    /**
     * Arrange input fields to strucural arrays.
     *
     * @TODO refactoring needed (bloated).
     * @return void
     */
    private function arrange()
    {
        $input = $this->all();

        foreach ($input['fields'] as $key => $value) {
            if (is_array($value)) {
                $input['fields']['relations'][$key] = $value;
            } else {
                $input['fields']['select'][] = $value;
            }

            unset($input['fields'][$key]);
        }

        if (!isset($input['fields']['relations'])) {
            $input['fields']['relations'] = [];
        }

        if (!isset($input['fields']['select'])) {
            $input['fields']['select'] = [];
        }

        if (isset($input['q'])) {
            $input['search']['q']       = $input['q'];
            $input['search']['fields']  = $this->searchFields;
            unset($input['q']);
        }

        if (isset($input['limit'], $input['offset'])) {
            $input['pagination']['limit']   = $input['limit'];
            $input['pagination']['offset']  = $input['offset'];
            unset($input['offset'], $input['limit']);
        }

        if (isset($input['order'])) {
            $order = explode('.', $input['order']);
            $input['order'] = [];
            $input['order']['field']      = $order[0];
            $input['order']['direction']  = $order[1];
        }

        if (property_exists($this, 'filterRules')) {
            $input['filter'] = [];
            $input['filter']['select'] = [];
            $input['filter']['select']['eq'] = [];
            $input['filter']['select']['ne'] = [];
            $input['filter']['select']['gt'] = [];
            $input['filter']['select']['lt'] = [];

            $input['filter']['relations'] = [];

            foreach ($this->filterRules as $filterRule) {
                if (isset($input[$filterRule])) {

                  $filterRuleParts = explode('_', $filterRule);

                  if (count($filterRuleParts) === 1) {
                      $input['filter']['select']['eq'][$filterRuleParts[0]] = $input[$filterRule];
                  } else if (count($filterRuleParts) === 2)  {
                      if (in_array($filterRuleParts[1], ['lt', 'gt', 'ne', 'eq'])) {
                          $input['filter']['select'][$filterRuleParts[1]][$filterRuleParts[0]] = $input[$filterRule];
                      } else {
                          if (!isset($input['filter']['relations'][$filterRuleParts[0]])) {
                              $input['filter']['relations'][$filterRuleParts[0]] = [];
                              $input['filter']['relations'][$filterRuleParts[0]]['eq'] = [];
                              $input['filter']['relations'][$filterRuleParts[0]]['ne'] = [];
                              $input['filter']['relations'][$filterRuleParts[0]]['gt'] = [];
                              $input['filter']['relations'][$filterRuleParts[0]]['lt'] = [];
                          }
                          $input['filter']['relations'][$filterRuleParts[0]]['eq'][$filterRuleParts[1]] = $input[$filterRule];
                      }
                  } else {
                      if (!isset($input['filter']['relations'][$filterRuleParts[0]])) {
                          $input['filter']['relations'][$filterRuleParts[0]] = [];
                          $input['filter']['relations'][$filterRuleParts[0]]['eq'] = [];
                          $input['filter']['relations'][$filterRuleParts[0]]['ne'] = [];
                          $input['filter']['relations'][$filterRuleParts[0]]['gt'] = [];
                          $input['filter']['relations'][$filterRuleParts[0]]['lt'] = [];
                      }
                      $input['filter']['relations'][$filterRuleParts[0]][$filterRuleParts[2]][$filterRuleParts[1]] = $input[$filterRule];
                  }

                  unset($input[$filterRule]);
                }
            }
        }

        if (property_exists($this, 'bodyRules')) {
            $input['body'] = [];
            foreach ($this->bodyRules as $bodyRule) {
                if (isset($input[$bodyRule])) {
                    $input['body'][$bodyRule] = $input[$bodyRule];
                    unset($input[$bodyRule]);
                }
            }
        }

        $this->replace($input);
    }

    /**
     * Set rules for order.
     *
     * @return ApiRequest
     */
    protected function order(array $order)
    {
        $this->checkAllowedMethod(['get']);

        $input = $this->all();
        if (!array_key_exists('order', $input)) {
            $input['order'] = 'id.asc';
        }
        $this->replace($input);

        $this->rules = array_merge($this->rules, [
            'order' => 'order:' . serialize($order)
        ]);

        return $this;
    }

    /**
     * Set rules for filter.
     *
     * @return ApiRequest
     */
    public function filter(array $filter = [])
    {
        $this->checkAllowedMethod(['get']);

        $this->rules = array_merge($filter, $this->rules);

        $this->filterRules = array_keys($filter);

        return $this->rules;
    }

    /**
     * Set rules for body.
     *
     * @return ApiRequest
     */
    public function body(array $body = [])
    {
        $this->checkAllowedMethod(['put', 'post']);

        $this->rules = array_merge($body, $this->rules);

        $this->bodyRules = array_keys($body);

        return $this->rules;
    }

    /**
     * Check if method is allowed to be called according to HTTP method. If method not
     * allowed to be called, throw an exception.
     *
     * @param array $allowedMethods
     * @return void
     */
    private function checkAllowedMethod(array $allowedMethods)
    {
        if (!in_array(strtolower($this->method()), $allowedMethods)) {
            throw new \Exception("Method not allowed on {$this->method()} request.", 500);
        }
    }

    /**
     * Get the proper failed validation response for the request.
     *
     * @param  array  $errors
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function response(array $errors)
    {
        return $this->respond(reset($errors)[0], 422);
    }
}
