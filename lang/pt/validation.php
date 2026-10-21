<?php

// Laravel's validation messages in European Portuguese, for the rules the app
// uses. The project language is pt, so any message a page doesn't write itself
// comes from here; anything missing falls back to English (fallback_locale).
return [
    'accepted' => 'Tem de aceitar este campo.',
    'array' => 'Este campo tem de ser uma lista.',
    'boolean' => 'Este campo tem de ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação não coincide.',
    'email' => 'Indique um email válido.',
    'file' => 'Escolha um ficheiro.',
    'image' => 'O ficheiro tem de ser uma imagem.',
    'in' => 'A opção escolhida não é válida.',
    'integer' => 'Indique um número inteiro.',
    'max' => [
        'array' => 'Escolha no máximo :max itens.',
        'file' => 'O ficheiro não pode ter mais de :max KB.',
        'numeric' => 'O valor não pode ser superior a :max.',
        'string' => 'O texto não pode ter mais de :max caracteres.',
    ],
    'mimes' => 'O ficheiro tem de ser do tipo: :values.',
    'mimetypes' => 'O ficheiro tem de ser do tipo: :values.',
    'min' => [
        'array' => 'Escolha pelo menos :min itens.',
        'file' => 'O ficheiro tem de ter pelo menos :min KB.',
        'numeric' => 'O valor tem de ser pelo menos :min.',
        'string' => 'O texto tem de ter pelo menos :min caracteres.',
    ],
    'numeric' => 'Indique um número.',
    'regex' => 'O formato não é válido.',
    'required' => 'Este campo é obrigatório.',
    'string' => 'Este campo tem de ser texto.',
    'unique' => 'Este valor já está a ser usado.',
    'uploaded' => 'Não foi possível carregar o ficheiro.',
    'url' => 'Indique um link válido.',
];
