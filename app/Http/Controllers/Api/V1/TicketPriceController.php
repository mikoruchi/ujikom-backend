<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ticket_prices;
use App\Models\Studio;
use Illuminate\Http\Request;

class TicketPriceController extends Controller
{
    public function index()
    {
        $prices = ticket_prices::with('studio')->get();
        return response()->json($prices);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'studio_id' => 'required|exists:studios,id',
            'weekday_price' => 'required|numeric',
            'weekend_price' => 'required|numeric',
            'holiday_price' => 'required|numeric',
        ]);

        $price = ticket_prices::create($data);

        return response()->json($price, 201);
    }

    public function update(Request $request, $id)
    {
        $price = ticket_prices::findOrFail($id);

        $data = $request->validate([
            'studio_id' => 'sometimes|exists:studios,id',
            'weekday_price' => 'sometimes|numeric',
            'weekend_price' => 'sometimes|numeric',
            'holiday_price' => 'sometimes|numeric',
            'status' => 'sometimes|in:Active,Inactive',
        ]);

        $price->update($data);

        return response()->json($price);
    }

    public function toggleStatus($id)
    {
        $price = ticket_prices::findOrFail($id);
        $price->status = $price->status === 'Active' ? 'Inactive' : 'Active';
        $price->save();

        return response()->json(['message' => 'Status updated', 'status' => $price->status]);
    }

    public function destroy($id)
    {
        $price = ticket_prices::findOrFail($id);
        $price->delete();

        return response()->json(['message' => 'Data harga tiket dihapus']);
    }
}
