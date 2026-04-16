<?php

namespace App\Http\Controllers;

use App\Models\Universite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UniversiteController extends Controller
{
    public function index()
    {
        return response()->json(Universite::all());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'abbreviation' => 'required|string|max:50|unique:universites',
            'ville' => 'nullable|string|max:150',
            'pays' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $universite = Universite::create($request->all());

        return response()->json($universite, 201);
    }

    public function show($id)
    {
        return response()->json(Universite::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $universite = Universite::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:255',
            'abbreviation' => 'sometimes|string|max:50|unique:universites,abbreviation,' . $id . ',id_universite',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $universite->update($request->all());

        return response()->json($universite);
    }

    public function destroy($id)
    {
        $universite = Universite::findOrFail($id);
        $universite->delete();
        return response()->json(null, 204);
    }

    public function search(Request $request)
    {
        $query = $request->input('q');
        
        $universities = Universite::query();
        
        if ($query) {
            $universities->where('nom', 'like', '%' . $query . '%')
                ->orWhere('abbreviation', 'like', '%' . $query . '%');
        }
        
        return response()->json($universities->limit(15)->get());
    }
}
