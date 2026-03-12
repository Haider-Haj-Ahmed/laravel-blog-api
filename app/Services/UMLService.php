<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UMLService
{
    protected string $model = 'gemini-2.5-flash';
    protected string $endpoint = 'https://generativelanguage.googleapis.com/v1/models';
    public function generateUML($description)
    {
        $instraction= "
        You are an expert UML generator. 
Your job is to convert natural language descriptions into valid UML code using the following DSL:

FORMAT:
@startuml
... UML code here ...
@enduml

RULES:
- Only output UML code, nothing else.
- Do NOT explain the diagram.
- Do NOT add comments.
- Follow PlantUML syntax exactly.
- Use classes, relationships, interfaces, enums, and notes when appropriate.
- Infer reasonable attributes and methods if the user does not specify them.
- Use correct relationship arrows:
    - Inheritance: A <|-- B
    - Composition: A *-- B
    - Aggregation: A o-- B
    - Association: A --> B
- Keep names clean and PascalCase.

USER DESCRIPTION:
'{{USER_INPUT}}'

OUTPUT:
Return ONLY the UML code between @startuml and @enduml.
";
        $instraction = str_replace('{{USER_INPUT}}', $description, $instraction);
    $url = "{$this->endpoint}/{$this->model}:generateContent?key=" . config('services.gemini2.key');

        $response = Http::post($url, [
            'contents' => [
                [
                    'parts'=>[
                        ['text' => $instraction],
                    ]
                    
                ]
            ]
        ]);
        // $encoded = urlencode($uml);
        // $url = "https://plantumlgen.pythonanywhere.com/generate_uml";
        // $response = Http::get($url, [
        //     'uml_text' => $encoded
        // ]);
        Log::error($response);
        $uml = $response['candidates'][0]['content']['parts'][0]['text'];
        Log::error($uml);
        $response = Http::withHeaders([
            'Content-Type' => 'text/plain'
        ])->send('POST', 'https://kroki.io/plantuml/png', [
            'body' => $uml
        ]);
        return $response->body();
    }
}