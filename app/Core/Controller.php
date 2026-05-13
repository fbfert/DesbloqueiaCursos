<?php

namespace App\Core;

class Controller
{
    protected function view($template, array $data = array())
    {
        return new Response(View::render($template, $data));
    }

    protected function json(array $data, $status = 200)
    {
        return Response::json($data, $status);
    }

    protected function redirect($url)
    {
        $this->preserveOldInputOnValidationFailure();
        return Response::redirect($url);
    }

    protected function submitAction(Request $request, $default = 'save_exit')
    {
        $action = trim((string) $request->input('submit_action', $default));

        return $action !== '' ? $action : $default;
    }

    protected function redirectAfterCrudSave(Request $request, array $result, array $options = array())
    {
        $idKey = isset($options['id_key']) ? (string) $options['id_key'] : 'id';
        $id = isset($result[$idKey]) ? (int) $result[$idKey] : 0;
        $action = $this->submitAction($request, isset($options['default_action']) ? (string) $options['default_action'] : 'save_exit');
        $isEdit = $id > 0 && !empty($request->input('id', 0));

        $createUrl = isset($options['create_url']) ? (string) $options['create_url'] : null;
        $editUrlPattern = isset($options['edit_url_pattern']) ? (string) $options['edit_url_pattern'] : null;
        $listUrl = isset($options['list_url']) ? (string) $options['list_url'] : null;
        $successMessages = isset($options['success_messages']) && is_array($options['success_messages']) ? $options['success_messages'] : array();
        $copyHandler = isset($options['copy_handler']) && is_callable($options['copy_handler']) ? $options['copy_handler'] : null;

        if (empty($result['ok'])) {
            return array(
                'ok' => false,
                'url' => $isEdit && $editUrlPattern ? $this->buildActionUrl($editUrlPattern, $id) : $createUrl,
                'message' => isset($result['message']) ? $result['message'] : null,
                'errors' => isset($result['errors']) ? $result['errors'] : array(),
            );
        }

        if ($action === 'save_copy' && $copyHandler) {
            return $copyHandler($id, $result);
        }

        if ($action === 'save_stay' && $editUrlPattern) {
            return array(
                'ok' => true,
                'url' => $this->buildActionUrl($editUrlPattern, $id),
                'message' => $this->resolveSuccessMessage($successMessages, $isEdit ? 'save_stay_edit' : 'save_stay_create', $isEdit ? 'save_stay' : 'save_stay'),
            );
        }

        if ($action === 'save_new' && $createUrl) {
            return array(
                'ok' => true,
                'url' => $createUrl,
                'message' => $this->resolveSuccessMessage($successMessages, $isEdit ? 'save_new_edit' : 'save_new_create', $isEdit ? 'save_new' : 'save_new'),
            );
        }

        return array(
            'ok' => true,
            'url' => $listUrl ?: ($editUrlPattern ? $this->buildActionUrl($editUrlPattern, $id) : ($createUrl ?: '/')),
            'message' => $this->resolveSuccessMessage($successMessages, $isEdit ? 'save_exit_edit' : 'save_exit_create', $isEdit ? 'save_exit' : 'save_exit'),
        );
    }

    protected function redirectAfterFormAction(Request $request, $stayUrl, $exitUrl = null)
    {
        $action = $this->submitAction($request, 'save_exit');

        if ($action === 'save_exit' && !empty($exitUrl)) {
            return $this->redirect($exitUrl);
        }

        return $this->redirect($stayUrl);
    }

    protected function buildActionUrl($pattern, $id)
    {
        return str_replace(array('{id}', ':id'), (string) $id, (string) $pattern);
    }

    protected function resolveSuccessMessage(array $messages, $primaryKey, $fallbackKey)
    {
        if (isset($messages[$primaryKey])) {
            return $messages[$primaryKey];
        }
        if (isset($messages[$fallbackKey])) {
            return $messages[$fallbackKey];
        }
        return isset($messages['default']) ? $messages['default'] : 'Registro salvo com sucesso.';
    }

    private function preserveOldInputOnValidationFailure()
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }

        if (!Session::hasFlash('errors')) {
            return;
        }

        if (Session::hasFlash('old_input')) {
            return;
        }

        $old = isset($_POST) && is_array($_POST) ? $_POST : array();
        if (!$old) {
            return;
        }

        $old = $this->removeSensitiveFields($old);
        Session::flash('old_input', $old);
    }

    private function removeSensitiveFields(array $values)
    {
        $sanitized = array();

        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if ($normalizedKey === '_token' || $normalizedKey === 'csrf_token') {
                continue;
            }

            if ($this->isSensitiveKey($normalizedKey)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->removeSensitiveFields($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey($key)
    {
        return strpos($key, 'senha') !== false
            || strpos($key, 'password') !== false
            || strpos($key, 'token') !== false;
    }
}
