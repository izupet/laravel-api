# Package not yet ready for usage

# laravel-api
This is Laravel 5.* package for developing RESTful APIs very simple and efficient. It provides validation with Laravel build-in validator with possibility of extension and some helpful model methods.

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


You are done.

## Usage

To create new resource use Laravel build in artisan command

```bash
$ php artisan make:controller CarsController
```

To create CarsController validation class run

```bash
$ php artisan api:request CarsApiRequest
```

This will create new file in app/Http/Requests folder
