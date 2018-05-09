<?php

namespace App\Forms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Forms\Models\Form;

class FormsController extends Controller
{
    /**
     * Decide what action should be taken based on postForm 'action'
     *
     * @param   mixed     $request
     * @return  mixed
     */
    protected function postForm(Request $request) {
        if ($request) {
            $this->validate($request, [
                'form_name' => 'required',
                'db_table' => 'required',
                'structure' => 'required'
            ]);
            if($request['action'] == 'add') {
                return $this->addForm($request);
            } else if($request['action'] == 'save') {
                return $this->saveForm($request);
            }
            return response()->json(['message' => 'No action set', 'status' => 500], 500);
        } else {
            return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
        }
    }

    /**
     * Create new instance of Form object and add it to the database
     *
     * @param   mixed     $request
     * @return  mixed
     */
    private function addForm(Request $request) {
        $this->validate($request, [
            'identifier' => 'required|unique:form'
        ]);
        $form = new Form();
        $form->name = $request['form_name'];
        $form->identifier = $request['identifier'];
        $form->db_table = $request['db_table'];
        $form->structure = json_encode($request['structure']);
        $form->save();
        return response()->json(['message' => 'Successfully added', 'status' => 200], 200);
    }

    /**
     * Save instance by 'identifier' in the database
     *
     * @param   mixed     $request
     * @return  mixed
     */
    private function saveForm(Request $request) {
        $checkId = Form::where('identifier', $request['identifier'])->pluck('id');
        if ($checkId->count() > 0) {
            if ($checkId[0] !== $request['id']) {
                return response()->json(['message' => 'The given data was invalid.','errors' => ['identifier' => ['The identifier has already been taken.']]], 422);
            }
        }
        Form::where('id', $request['id'])->update([
            'name' => $request['form_name'],
            'identifier' => $request['identifier'],
            'db_table' => $request['db_table'],
            'structure' => json_encode($request['structure'])
        ]);
        return response()->json(['message' => 'Successfully saved', 'status' => 200], 200);
    }

    /**
     * Get all forms
     *
     * @return  mixed
     */
    protected function getForms() {
        $forms = Form::all();
        return response()->json($forms, 200);
    }

    /**
     * Get form by 'id'
     *
     * @param   integer    $id
     * @return  mixed
     */
    protected function getForm($id) {
        $form = Form::where([
            ['deleted', 'false'],
            ['id', $id]
        ])->get();
        return response()->json($form, 200);
    }

    /**
     * Get an array of form 'identifiers'
     *
     * @return  mixed
     */
    protected function getFormIds() {
        $ids = Form::select('identifier')->get();
        return response()->json($ids, 200);
    }
}