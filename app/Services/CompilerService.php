<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CompilerService
{
    public function run($language, $code, $stdin = "")
    {
        $compiler = $this->compilerMap()[strtolower($language)] ?? null;

        if (!$compiler) {
            return [
                "status" => "error",
                "message" => "Unsupported language: $language"
            ];
        }

        $payload = [
            "compiler" => $compiler,
            "input" => $stdin,
            "code" => $code
        ];

        $response = Http::withHeaders([
            "Authorization" =>env("ONECOMPILERIO_API_KEY"),
            "Content-Type" => "application/json"
        ])->post("https://api.onlinecompiler.io/api/run-code-sync", $payload);

        return $response->json();
    }

    // private function getExtension($language)
    // {
    //     return [
    //         "python" => "py",
    //         "php" => "php",
    //         "javascript" => "js",
    //         "c" => "c",
    //         "cpp" => "cpp",
    //         "java" => "java",
    //         "go" => "go",
    //         "rust" => "rs",
    //     ][$language] ?? "txt";
    // }
    private function compilerMap()
    {
        return [

            // Python
            "python"      => "python-3.14",
            "python3"     => "python-3.14",
            "py"          => "python-3.14",

            // C (GCC 15)
            "c"           => "gcc-15",
            "gcc"         => "gcc-15",

            // C++ (G++ 15)
            "cpp"         => "g++-15",
            "c++"         => "g++-15",
            "g++"         => "g++-15",

            // Java (OpenJDK 25)
            "java"        => "openjdk-25",

            // C# (.NET SDK 9)
            "csharp"      => "dotnet-csharp-9",
            "c#"          => "dotnet-csharp-9",
            "cs"          => "dotnet-csharp-9",

            // F# (.NET SDK 9)
            "fsharp"      => "dotnet-fsharp-9",
            "f#"          => "dotnet-fsharp-9",

            // PHP 8.5
            "php"         => "php-8.5",

            // Ruby 4.0
            "ruby"        => "ruby-4.0",
            "rb"          => "ruby-4.0",

            // Haskell GHC 9.12
            "haskell"     => "haskell-9.12",
            "hs"          => "haskell-9.12",

            // Go 1.26
            "go"          => "go-1.26",
            "golang"      => "go-1.26",

            // Rust 1.93
            "rust"        => "rust-1.93",
            "rs"          => "rust-1.93",

            // TypeScript (Deno)
            "typescript"  => "typescript-deno",
            "ts"          => "typescript-deno",
        ];
    }
}
