<?php

namespace App\Forms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Forms\Models\Form;

class FormsController extends Controller
{
    /**
     * Save an existing form in the database
     *
     * @param   mixed     $request
     * @return  mixed
     */
    protected function saveForm(Request $request) {
        if ($request) {
            $data = $request['form'];
            Form::where('id', $data['id'])->update([
                'name' => $data['name'],
                'identifier' => $data['identifier'],
                'structure' => json_encode($data['structure'])
            ]);
            return response()->json(['message' => 'Successfully saved', 'status' => 200], 200);
        } else {
            return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
        }
    }

    /**
     * Save a new form in the database
     *
     * @param   mixed     $request
     * @return  mixed
     */
    protected function saveNewForm(Request $request) {
        if ($request) {
            $data = $request['form'];
            return $data;
        } else {
            return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
        }
    }

    protected function newForm(Request $request) {
        if ($request) {
            $data = $request['form'];
            $form = new Form();
            $form->identifier = $data['identifier'];
            $form->name = $data['name'];
            $form->structure = '{}';
            if ($form->save()) {
                return response()->json(['message' => 'New form successfully saved', 'id' => $form->id, 'status' => 200], 200);
            }
            return response()->json(['message' => 'Could not save the form', 'status' => 500], 500);
        } else {
            return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
        }
    }

    /**
     * Get all forms
     *
     * @return  mixed
     */
    protected function getForms() {
        $forms = Form::where('deleted', 'false')->get();
        return response()->json($forms, 200);
    }

    /**
     * Get form fields by id
     *
     * @param  integer    $id
     */
    protected function getFormFields($id) {
        $fields = Form::where('identifier', $id);
        $output = response()->json(['message' => 'Could not find form for \''.$id.'\'', 'status' => 400], 400);
        if ($fields->count() > 0) {
            $fields = json_decode($fields->first()['structure'], true);
            $output = [];
            foreach ($fields as $id => $field) {
                if ($field['type'] != 'title' && $field['type'] != 'subtitle') {
                    array_push($output, array('label' => $field['name'], 'value' => $id));
                }
            }
        }
        return $output;
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

    protected function deleteForm($id) {
      if (Form::where('id', $id)->update(['deleted' => 'true'])) {
        return response()->json(['message' => 'Form successfully deleted', 'status' => 200], 200);
      }
      return response()->json(['message' => 'Something went wrong', 'status' => 500], 500);
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

    protected function checkUniqueIdentifier($id) {
        $count = Form::where('identifier', $id)->count();
        if ($count > 0) {
            return 'true';
        }
        return 'false';
    }
}