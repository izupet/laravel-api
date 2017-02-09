<?php

namespace Izupet\Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ApiResponseTest extends TestCase
{
    public function __construct()
    {
        $this->apiResponse = $this->getMockForTrait(\Izupet\Api\Traits\ApiResponse::class);
    }

    /**
     * Test responses. Check if all parts of response are present (also headers).
     *
     * @return void
     */
    public function testSuccessResponse()
    {
        $message = 'Ok.';

        foreach ([200, 201, 304, 400, 401, 404, 500] as $successHTTPStatusCode) {
            $rsp = $this->apiResponse->respond($message, $successHTTPStatusCode);
            $this->commonAserts('Ok.', $successHTTPStatusCode, $rsp);
        }
    }

    private function commonAserts($message, $status, $rsp)
    {
        $decodedRsp = json_decode($rsp->content(), true);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $rsp);
        $this->assertEquals($status, $rsp->getStatusCode());
        $this->assertTrue($rsp->headers->has('content-type'));
        $this->assertEquals($rsp->headers->get('content-type'), 'application/json');
        $this->assertArrayHasKey('meta', $decodedRsp);
        $this->assertTrue($this->apiResponse->getStatus($rsp->getStatusCode()) === 'success' ?
            array_key_exists('data', $decodedRsp) : !array_key_exists('data', $decodedRsp));
        $this->assertArrayHasKey('message', $decodedRsp['meta']);
        $this->assertArrayHasKey('status', $decodedRsp['meta']);
        $this->assertEquals($decodedRsp['meta']['message'], $message);
        $this->assertEquals($decodedRsp['meta']['status'], $this->apiResponse->getStatus($status));
        $this->assertEquals($decodedRsp['meta']['code'], $rsp->getStatusCode());
    }
}
