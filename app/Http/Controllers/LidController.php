<?php

namespace App\Http\Controllers;

use App\Models\Lid;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "VizzanoERP uchun API hujjatlari",
    title: "VizzanoERP API",
    contact: new OA\Contact(email: "support@vizzanoerp.com")
)]
#[OA\Server(url: 'http://192.168.0.107:8000/api', description: 'Local server')]
#[OA\Server(url: 'http://staging.example.com', description: 'Staging server')]
#[OA\Server(url: 'http://example.com', description: 'Production server')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    name: 'Authorization',
    in: 'header',
    scheme: 'bearer'
)]

class LidController extends Controller
{
    /**
     * @OA\Get(
     *      path="/lids",
     *      operationId="getLidsList",
     *      tags={"Lids"},
     *      summary="Get list of lids",
     *      description="Returns a list of all lids in the system.",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Lid"))
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Lids not found"
     *      )
     * )
     */
    public function index()
    {
        $lids = Lid::all();
        return response()->json($lids);
    }

    /**
     * @OA\Post(
     *      path="/lids",
     *      operationId="storeLid",
     *      tags={"Lids"},
     *      summary="Store a new lid",
     *      description="Creates a new lid in the system.",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name", "phone", "address"},
     *              @OA\Property(property="name", type="string", example="John Doe", description="Name of the lid"),
     *              @OA\Property(property="phone", type="string", example="998901234567", description="Phone number of the lid"),
     *              @OA\Property(property="address", type="string", example="Tashkent", description="Address of the lid"),
     *              @OA\Property(property="comment", type="string", example="Comment", description="Optional comment for the lid")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Lid created successfully",
     *          @OA\JsonContent(ref="#/components/schemas/Lid")
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Failed to create lid"
     *      )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'phone' => 'required',
            'address' => 'required',
        ]);
        $lid = Lid::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'comment' => $request->comment,
            'status' => 'active',
        ]);

        if ($lid) {
            return response()->json([
                'message' => 'Lid created successfully',
                'lid' => $lid,
            ]);
        } else {
            return response()->json([
                'message' => 'Lid not created',
            ]);
        }
    }

    /**
     * @OA\Patch(
     *      path="/lids/{lid}",
     *      operationId="updateLid",
     *      tags={"Lids"},
     *      summary="Update an existing lid",
     *      description="Updates an existing lid's details.",
     *      @OA\Parameter(
     *          name="lid",
     *          in="path",
     *          description="ID of the lid to update",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name", "phone", "address"},
     *              @OA\Property(property="name", type="string", example="John Doe", description="Name of the lid"),
     *              @OA\Property(property="phone", type="string", example="998901234567", description="Phone number of the lid"),
     *              @OA\Property(property="address", type="string", example="Tashkent", description="Address of the lid"),
     *              @OA\Property(property="comment", type="string", example="Updated comment", description="Updated comment of the lid")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Lid updated successfully",
     *          @OA\JsonContent(ref="#/components/schemas/Lid")
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Lid not found"
     *      )
     * )
     */
    public function update(Request $request, Lid $lid)
    {
        $request->validate([
            'name' => 'required',
            'phone' => 'required',
            'address' => 'required',
        ]);
        $lid->name = $request->name;
        $lid->phone = $request->phone;
        $lid->address = $request->address;
        $lid->status = $request->status;
        $lid->comment = $request->comment;
        $lid->save();
        return response()->json([
            'message' => 'Lid updated successfully',
            'lid' => $lid,
        ]);
    }

    /**
     * @OA\Delete(
     *      path="/lids/{lid}",
     *      operationId="deleteLid",
     *      tags={"Lids"},
     *      summary="Delete an existing lid",
     *      description="Deletes an existing lid from the system.",
     *      @OA\Parameter(
     *          name="lid",
     *          in="path",
     *          description="ID of the lid to delete",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Lid deleted successfully"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Lid not found"
     *      )
     * )
     */

    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required',
        ]);
        $lids = Lid::where('name', 'like', '%' . $request->query . '%')
            ->orWhere('phone', 'like', '%' . $request->query . '%')
            ->orWhere('address', 'like', '%' . $request->query . '%')
            ->get();
        return response()->json($lids);
    }
}
