<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    const URL_BASE_STACKOVERFLOW = 'https://api.stackexchange.com/2.3/';
    const VALIDATOR_TAG = 'validator';
    const DEFAULT_VALUE_TAG = 'defaultValue';
    const DATA_TYPE = 'dataType';

    public array $paramsConfig = [
        'tagged' => [
            self::VALIDATOR_TAG     => 'required|max:255',
            self::DEFAULT_VALUE_TAG => '',
            self::DATA_TYPE         => 'string'
        ],
        'todate' => [
            self::VALIDATOR_TAG     => 'date_format:Ymd|after:fromdate',
            self::DEFAULT_VALUE_TAG => null,
            self::DATA_TYPE => 'date'
        ],
        'fromdate' => [
            self::VALIDATOR_TAG     => 'date_format:Ymd|before:todate',
            self::DEFAULT_VALUE_TAG => null,
            self::DATA_TYPE => 'date'
        ]

    ];

    /**
     * @param Request $request
     * @return JsonResponse
     */

    public function getQuestions(Request $request): JsonResponse
    {

        try{

            $this->validateParams($request);

            $params = $this->configureParamsForGetQuestions($request);

            $info = $this->getQuestionFromStackOverFlow($params);

            return $this->returnResponse($info);

        } catch (ValidationException $ex){
            return $this->returnResponseValidationError($ex->validator->errors()->getMessages());
        }
        catch (GuzzleException $ex) {
            return $this->returnResponseError($ex->getMessage());
        }
        catch (Exception $ex) {
            return $this->returnResponseError($ex->getMessage());
        }

    }

    /**
     * @param Request $request
     * @throws ValidationException
     */
    private function validateParams(Request $request) {

        $validation = [];

        foreach ($this->paramsConfig as $param => $info) {
            $validation[$param] = $info[self::VALIDATOR_TAG];
        };

        $validator = Validator::make($request->all(), $validation);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

    }

    /**
     * @param $request
     * @return array
     */
    private function configureParamsForGetQuestions($request): array
    {

        $params = [
            'site'  => 'stackoverflow',
        ];

        foreach ($this->paramsConfig as $paramName => $info) {
            $value = $request->get($paramName, $info[self::DEFAULT_VALUE_TAG]);
            if ($value){
                $params[$paramName] = $info[self::DATA_TYPE] === 'date' ? $this->convertDateValue($value) : $value;
            }
        }

        return $params;

    }

    /**
     * @throws Exception|GuzzleException
     */
    private function getQuestionFromStackOverFlow($params){

        try {
            $client = new Client([
                'base_uri' => self::URL_BASE_STACKOVERFLOW,
            ]);
            $res = $client->request('GET', 'questions', [
                'query' => $params
            ]);

            return json_decode($res->getBody(),true);
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }

    }

    /**
     * @param $value
     * @return false|int
     */
    private function convertDateValue($value): bool|int
    {
        return strtotime(date("Y-m-d", strtotime($value)));
    }

    /**
     * @param $data
     * @return JsonResponse
     */
    private function returnResponse($data): JsonResponse
    {

        return response()->json([
            'status' => 'Success',
            'message' => 'Success',
            'data' => $data
        ])->setStatusCode(200);

    }

    /**
     * @param $messages
     * @param int $statusCode
     * @return JsonResponse
     */
    private function returnResponseError($messages, int $statusCode = 500): JsonResponse
    {
        Log::error($messages);
        return response()->json([
            'status' => 'Failed',
            'message' => "An exception has occurred.",
            'messages' => $messages,
        ])->setStatusCode($statusCode);

    }

    /**
     * @param $validationErrors
     * @return JsonResponse
     */
    private function returnResponseValidationError($validationErrors): JsonResponse
    {

        return response()->json([
            'status' => 'Failed',
            'message' => "Some errors on the data inputted.",
            'errors' => $validationErrors
        ])->setStatusCode(400);

    }
}
