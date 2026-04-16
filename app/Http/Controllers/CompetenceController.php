<?php

namespace App\Http\Controllers;

use App\Models\Competence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompetenceController extends Controller
{
    public function index()
    {
        return response()->json(Competence::all());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:120|unique:competences',
            'type' => 'required|in:programming,framework,library,tool,soft skills,other',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $competence = Competence::create($request->all());

        return response()->json($competence, 201);
    }

    public function show($id)
    {
        return response()->json(Competence::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $competence = Competence::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:120|unique:competences,nom,' . $id . ',id_competence',
            'type' => 'sometimes|in:programming,framework,library,tool,soft skills,other',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $competence->update($request->all());

        return response()->json($competence);
    }

    public function destroy($id)
    {
        $competence = Competence::findOrFail($id);
        $competence->delete();
        return response()->json(null, 204);
    }
}
