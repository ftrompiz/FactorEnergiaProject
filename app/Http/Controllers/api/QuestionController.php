<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            self::VALIDATOR_TAG     => 'date',
            self::DEFAULT_VALUE_TAG => null,
        ],
        'fromdate' => [
            self::VALIDATOR_TAG     => 'date',
            self::DEFAULT_VALUE_TAG => null,
        ]

    ];

    /**
     * @param Request $request
     */
    public function getQuestion(Request $request){

        try{

            $this->validateParams($request);

            $params = $this->configureParamsForGetQuestions($request);

            $info = $this->getQuestionFromStackOverFlow($params);

            return $this->returnResponse($info);

        } catch (ValidationException $ex){
            return $this->returnResponseError($ex->validator->errors()->getMessages());
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
                $params[$paramName] = $value;
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
     * @param $data
     * @return JsonResponse
     */
    private function returnResponse($data): JsonResponse
    {

        return response()->json([
            'status' => true,
            'data' => $data
        ]);

    }

    /**
     * @param $messages
     * @return JsonResponse
     */
    private function returnResponseError($messages): JsonResponse
    {
        return response()->json([
            'message' => "Validation Errors",
            'status' => false,
            'errors' => $messages
        ]);

    }
}
