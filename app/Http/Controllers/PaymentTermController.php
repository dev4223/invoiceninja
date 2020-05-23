<?php

namespace App\Http\Controllers;

use App\Factory\PaymentTermFactory;
use App\Http\Requests\PaymentTerm\CreatePaymentTermRequest;
use App\Http\Requests\PaymentTerm\DestroyPaymentTermRequest;
use App\Http\Requests\PaymentTerm\ShowPaymentTermRequest;
use App\Http\Requests\PaymentTerm\StorePaymentTermRequest;
use App\Http\Requests\PaymentTerm\UpdatePaymentTermRequest;
use App\Models\PaymentTerm;
use App\Transformers\PaymentTermTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

class PaymentTermController extends BaseController
{
    use MakesHash;

    protected $entity_type = PaymentTerm::class;

    protected $entity_transformer = PaymentTermTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     *      @OA\Get(
     *      path="/api/v1/payment_terms",
     *      operationId="getPaymentTerms",
     *      tags={"payment_terms"},
     *      summary="Gets a list of payment terms",
     *      description="Lists payment terms",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of payment terms",
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/PaymentTerm"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function index()
    {
        $payment_terms = PaymentTerm::whereCompanyId(auth()->user()->company()->id)->orWhere('company_id', null);

        return $this->listResponse($payment_terms);
    }    

    /**
     * Show the form for creating a new resource.
     *
     * @param      \App\Http\Requests\Payment\CreatePaymentTermRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/payment_terms/create",
     *      operationId="getPaymentTermsCreate",
     *      tags={"payment_terms"},
     *      summary="Gets a new blank PaymentTerm object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank PaymentTerm object",
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function create(CreatePaymentTermRequest $request)
    {
        $payment_term = PaymentTermFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($payment_term);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param      \App\Http\Requests\Payment\StorePaymentRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/payment_terms",
     *      operationId="storePaymentTerm",
     *      tags={"payment_terms"},
     *      summary="Adds a Payment",
     *      description="Adds a Payment Term to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\RequestBody(
     *         description="The payment_terms request",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/PaymentTerm"),
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved Payment object",
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/PaymentTerm"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function store(StorePaymentTermRequest $request)
    {
        $payment_term = PaymentTermFactory::create(auth()->user()->company()->id, auth()->user()->id);
        $payment_term->fill($request->all());
        $payment_term->save();

        return $this->itemResponse($payment_term->fresh());
    }

    /**
     * @OA\Get(
     *      path="/api/v1/payment_terms/{id}",
     *      operationId="showPaymentTerm",
     *      tags={"payment_terms"},
     *      summary="Shows a Payment Term",
     *      description="Displays an Payment Term by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Payment Term Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Payment Term object",
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/PaymentTerm"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function show(ShowPaymentTermRequest $request, PaymentTerm $payment_term)
    {
        return $this->itemResponse($payment_term);
    }


    /**
     * @OA\Get(
     *      path="/api/v1/payment_terms/{id}/edit",
     *      operationId="editPaymentTerms",
     *      tags={"payment_terms"},
     *      summary="Shows an Payment Term for editting",
     *      description="Displays an Payment Term by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Payment Term Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Payment object",
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/PaymentTerm"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function edit(EditPaymentRequest $request, Payment $payment)
    {
        return $this->itemResponse($payment);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param      \App\Http\Requests\PaymentTerm\UpdatePaymentTermRequest  $request  The request
     * @param      \App\Models\PaymentTerm                                  $payment_term   The payment term
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Put(
     *      path="/api/v1/payment_terms/{id}",
     *      operationId="updatePaymentTerm",
     *      tags={"payment_terms"},
     *      summary="Updates a Payment Term",
     *      description="Handles the updating of an Payment Termby id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Payment Term Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Payment Term object",
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/PaymentTerm"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function update(UpdatePaymentTermRequest $request, PaymentTerm $payment_term)
    {
        $payment_term->fill($request->all());
        $payment_term->save();

        return $this->itemResponse($payment_term->fresh());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param      \App\Http\Requests\PaymentTerm\DestroyPaymentTermRequest  $request
     * @param      \App\Models\PaymentTerm                                   $payment_term
     *
     * @return     \Illuminate\Http\Response
     *
     *
     * @OA\Delete(
     *      path="/api/v1/payment_terms/{id}",
     *      operationId="deletePaymentTerm",
     *      tags={"payment_termss"},
     *      summary="Deletes a Payment Term",
     *      description="Handles the deletion of an PaymentTerm by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Payment Term Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function destroy(DestroyPaymentTermRequest $request, PaymentTerm $payment_term)
    {

        $payment_term->delete();

        return response()->json([], 200);
    }

}
