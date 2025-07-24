<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Frontend;
use Illuminate\Http\Request;
use File;

class FrontEndManagementController extends Controller
{
    public function index()
    {
        $jsonUrl = resource_path('views/admin/settings.json');
        $sections = json_decode(file_get_contents($jsonUrl), true);
        ksort($sections);

        return view('admin.frontend-management.index', compact('sections'));
    }

    public function section($key)
    {
        $lang_code = request('lang_code', 'en');
        $jsonUrl = resource_path('views/admin/settings.json');
        $sections = json_decode(file_get_contents($jsonUrl), true);

        if (!isset($sections[$key])) {
            abort(404, "Section not found for key: $key");
        }

        $section = $sections[$key];
        $contentType = isset($section['content']) ? 'content' : (isset($section['element']) ? 'element' : null);

        if (!$contentType) {
            abort(404, "Content or Element not found for section: $key");
        }

        $dataKeys = $key . '.' . $contentType;
        $content = $section[$contentType];
        $frontend = Frontend::where('data_keys', $dataKeys)->first();

        // Initialize data values
        $dataValues = $frontend ? $frontend->data_values : [];

        // Handle translations for non-English languages
        if ($lang_code !== 'en' && $frontend) {
            $translations = json_decode($frontend->data_translations, true) ?? [];
            $translation = collect($translations)->firstWhere('language_code', $lang_code);

            if ($translation) {
                $dataValues = array_merge($frontend->data_values, $translation['values'] ?? []);
            } else {
                // Add new translation structure if not found
                $translations[] = [
                    'language_code' => $lang_code,
                    'values' => array_diff_key($frontend->data_values, ['images' => '']),
                ];
                $frontend->data_translations = json_encode($translations);
                $frontend->save();
            }
        }

        $imageCount = isset($content['images']) ? count($content['images']) : 0;
        $pageTitle = $section['name'] ?? trans('translate.Frontend Management');

        return view('admin.frontend-management.edit', compact('pageTitle', 'key', 'content', 'dataValues', 'frontend', 'contentType', 'imageCount', 'lang_code'));
    }

    public function store(Request $request, $key, $id = null)
    {
        $lang_code = $request->get('lang_code');

        if (!$lang_code) {
            return back()->with('error', 'Language code is required');
        }

        $jsonUrl = resource_path('views/admin/settings.json');
        $sections = json_decode(file_get_contents($jsonUrl), true);

        if (!isset($sections[$key])) {
            abort(404, "Section not found for key: $key");
        }

        $section = $sections[$key];
        $contentType = isset($section['content']) ? 'content' : (isset($section['element']) ? 'element' : null);

        if (!$contentType) {
            abort(404, "Content or Element not found for section: $key");
        }

        $dataKeys = $key . '.' . $contentType;
        $frontend = $id ? Frontend::findOrFail($id) : new Frontend();

        // Process form data including nested structures
        $formData = $this->processFormData($request->except(['_token', '_method', 'type', 'lang_code']));

        // Handle image uploads
        $imageData = $this->handleImageUploads($request, $section[$contentType]['images'] ?? [], $frontend->data_values['images'] ?? []);

        $translations = json_decode($frontend->data_translations, true) ?? [];

        if ($lang_code === 'en') {
            // Handle English content
            $finalData = $formData;
            if (!empty($imageData)) {
                $finalData['images'] = $imageData;
            } elseif (isset($frontend->data_values['images'])) {
                $finalData['images'] = $frontend->data_values['images'];
            }

            $frontend->data_values = $finalData;
            $this->updateTranslation($translations, 'en', $formData);
        } else {
            // Handle non-English content
            $this->updateTranslation($translations, $lang_code, $formData);

            if (empty($frontend->data_values)) {
                $frontend->data_values = ['images' => $imageData];
                if (!$this->hasLanguageTranslation($translations, 'en')) {
                    $translations[] = ['language_code' => 'en', 'values' => []];
                }
            }
        }

        if (!$frontend->data_keys) {
            $frontend->data_keys = $dataKeys;
        }

        $frontend->data_translations = json_encode($translations);
        $frontend->save();

        $notify_message = trans('translate.Update successfully');
        $notify_message = array('message' => $notify_message, 'alert-type' => 'success');
        return redirect()->back()->with($notify_message);
    }

    private function processFormData($data)
    {
        $processed = [];
        foreach ($data as $key => $value) {
            if (preg_match('/^([^[]+)(?:\[([^\]]+)\])+/', $key, $matches)) {
                $keys = [];
                $keys[] = $matches[1];
                preg_match_all('/\[([^\]]+)\]/', $key, $nestedKeys);
                $keys = array_merge($keys, $nestedKeys[1]);

                $current = &$processed;
                foreach ($keys as $k) {
                    if (!isset($current[$k])) {
                        $current[$k] = [];
                    }
                    $current = &$current[$k];
                }
                $current = $value;
            } else {
                $processed[$key] = $value;
            }
        }
        return $processed;
    }

    private function handleImageUploads($request, $configuredImages, $existingImages)
    {
        $imageData = [];

        if (empty($configuredImages)) {
            return $existingImages ?? [];
        }

        foreach ($configuredImages as $imageKey => $imageDetails) {
            if ($request->hasFile($imageKey)) {
                $image = $request->file($imageKey);
                $imageName = time() . '_' . $imageKey . '.' . $image->getClientOriginalExtension();

                $oldFile = $existingImages[$imageKey] ?? null;
                if ($oldFile && File::exists(public_path($oldFile))) {
                    unlink(public_path($oldFile));
                }

                $image->move(public_path('uploads/website-images'), $imageName);
                $imageData[$imageKey] = 'uploads/website-images/' . $imageName;
            } elseif (isset($existingImages[$imageKey])) {
                $imageData[$imageKey] = $existingImages[$imageKey];
            }
        }

        return $imageData;
    }

    private function updateTranslation(&$translations, $langCode, $values)
    {
        $exists = false;
        foreach ($translations as &$translation) {
            if ($translation['language_code'] === $langCode) {
                $translation['values'] = $values;
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $translations[] = [
                'language_code' => $langCode,
                'values' => $values
            ];
        }
    }

    private function hasLanguageTranslation($translations, $langCode)
    {
        foreach ($translations as $translation) {
            if ($translation['language_code'] === $langCode) {
                return true;
            }
        }
        return false;
    }
}
