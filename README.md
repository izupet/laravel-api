# laravel-api - [example repo](https://github.com/izupet/laravel-api-example)
This is Laravel 5.* package for developing RESTful APIs very simple and efficient. It provides validation with Laravel build-in validator with possibility of extension and some helpful model methods. It is fully customizable. It is really amazing because you can define partial responses and decrease response data significantly. All other features like ordering, searching, filtering are also provided.  

## Prerequisite

PHP version >= 5.5 <br>
Laravel framework 5 and up

## Installation
First you need to install this package using composer
```bash
composer require izupet/api
```

Add service provider in app.php file:

```php
Izupet\Api\Providers\ApiServiceProvider::class
```

After composer finishes its work, run this artisan command to generate config file
```bash
$ php artisan vendor:publish
```

In this api.php config file you can set default limit, offset, etc.


You are done. Package is installed and successfully set up and ready for use.

## Usage

To create new resource use Laravel build-in artisan command

```bash
$ php artisan make:controller CarsController
```

To create CarsRequest validation class run

```bash
$ php artisan api:request CarsRequest
```

This will create new file in app/Http/Requests folder. In controller you can then use this CarsRequest as follows
```php

use App\Http\Requests\CarsRequest;
use Izupet\Api\Traits\ApiResponse;

class CarsController extends Controller
{
    use ApiResponse;
    
    public function index(CarsRequest $request)
    {
        //
        
        return $this->respond('Ok', 200, []);
    }
}
```
This $request variable will be already fully equiped with validated user input. Using $request->all() will return validated and structured user input ready for further processing. As you can see, ApiResponse trait is used in this controller also. This trait is meant to be used everywhere you need to return response (controllers, exception handlers, etc.). Three main methods are there ready to be called:

1. respondCollection($data) for collections - usually used with GET medhod
2. respondOne($data) for single objects - usualy used with PUT or POST methods
3. respond($message, $HTTPStatusCode, $data = null, array $extras = []) - main respond method for custom responses

All responses has meta propery which has alway present status, message and code properties. Status is generated automaticaly according to code (success or error). If response is successful, data property is added to response otherwise skipped.

Example of respondCollection response:

```json
{
    "meta": {
        "status": "success",
        "message": "Ok.",
        "code": 200,
        "total": 503,
        "limit": 10,
        "offset": 0
    },
    "data": []
}
```

Example of respondOne response:

```json
{
    "meta": {
        "status": "success",
        "message": "Ok.",
        "code": 200
    },
    "data": []
}
```

Example of respond error response:

```json
{
    "meta": {
        "status": "error",
        "message": "Custom error message.",
        "code": 400
    }
}
```

Anytime suppressResponseHttpStatusCode param is present in query string response will be returned with status code 200. This is comes handy sometines when dealing with special applications. The code in response meta propery remains nonsuppressed.

The most powerful thing of this library are validation request classes as we created earlier (see above). Each class comes with four main methods:

 - getRules() - set up rules for GET method
 - postRules() - set up rules for POST method
 - putRules() - set up rules for PUT method
 - deleteRules() - set up rules for DELETE method

Example of getRules method filled with all available helper rules:

```php
public function getRules()
{
    return $this
        ->search(['model', 'brand'])
        ->pagination()
        ->fields(['id', 'model', 'brand', 'type', 'createdAt', 'images' => [
            'path', 'id', 'position', 'createdAt'
        ]])
        ->order(['model', 'brand', 'id'])
        ->filter([
            'type'                => 'in:coupe,convertable,suv,sedan',
            'images_position'     => 'numeric|min:0|max:4',
            'images_position_nq'  => 'numeric|min:0|max:4',
            'images_path'         => 'max:99',
            'brand'               => 'alpha',
            'images_createdAt_gt' => 'date_format:Y-m-d',
            'createdAt_lt'        => 'date_format:Y-m-d',
            'id'                  => 'numeric'
        ]);
}
```
So rules for GET method are set, let's dig in. 

 - search - method accepts array of fileds as parmater (fields available to be searched through) 

```url
/cars?q=BMW
```

 - pagination - allows endpoint to use limit and offset params (default values set in config)

```url
/cars?limit=20&offset=40
```

 - fields - fields that can be returned in response. Use suba arrays to set relations fields

```url
/cars?fields=id,model,images.path
```

 - order - fields that can be ordered by

```url
/cars?order=id.desc
```

 - filter - fields that can be filtered by. There you can use build-in validator or custom extensions. Underscores in names represents dots in URL. Sufixes (ne = not equal, gt = greather than, lt = lower than) are also important feature that perfects this API. You can also filter by relation fields. 

```url
/cars?type=sedan&images.createdAt.gt=2017-01-01
```

After validation you can access your validated request in controller. Let see how it looks request if we do dd($request->all());

```php
array:6 [▼
  "fields" => array:2 [▶]
  "order" => array:2 [▶]
  "search" => array:2 [▶]
  "pagination" => array:2 [▶]
  "filter" => array:2 [▶]
  "body" => array:1 [▶]
]
```
All params are fine strucured and prepared to be used in services/repositories. For that purpose there are a couple of helpful methods that can be used in connection with Eloquent.

Example of repository using this methods:

```php
class CarRepository
{
    public function __construct(
        \App\Car $car
    )
    {
        $this->car = $car;
    }

    public function all(array $input)
    {
        return $this->car->apiGet('fields', 'pagination', 'search', 'order', 'filter', 'relations', $input);
    }

    public function create(array $input)
    {
        return $this->car->apiCreate('fields', $input);
    }

    public function update(array $input, \App\Car $car)
    {
        return $car->apiUpdate('fields', 'relations', $input);
    }

    public function delete(\App\Car $car)
    {
        return $car->apiDelete();
    }
}
```

Calling 
```php
\App\Cars::apiGet('fields', 'pagination', 'search', 'order', 'filter', 'relations', $input)
```

is equal to
```php
    $cars = \App\Cars::apiFields($input['fields']['select'], true)
        ->apiPagination($input['pagination'])
        ->apiSearch($input['search'])
        ->apiOrder($input['order'])
        ->apiFilter($input['filter']['select']);
    
   $cars->apiRelations($cars, $input['fields']['relations'], $input['filter']['relations']);
```
Second parameter is for retrieving total number of records matching filter criteria.

Helpers for create, update and delete:

```php
public function create(array $input)
{
    return $this->car->apiCreate('fields', $input);
}

public function update(array $input, \App\Car $car)
{
    return $car->apiUpdate('fields', 'relations', $input);
}

public function delete(\App\Car $car)
{
    return $car->apiDelete();
}
```







