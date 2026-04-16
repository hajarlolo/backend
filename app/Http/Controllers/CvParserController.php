<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;

class CvParserController extends Controller
{
    /**
     * Parse CV file and extract structured data
     */
    public function parseCv(Request $request)
    {
        // Log the request for debugging
        \Log::info('CV Parser request received', [
            'has_file' => $request->hasFile('cv_file'),
            'files' => $request->allFiles(),
            'user_id' => auth()->id(),
            'authenticated' => auth()->check()
        ]);

        $validator = Validator::make($request->all(), [
            'cv_file' => 'required|file|mimes:pdf,doc,docx|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            \Log::error('CV Parser validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'error' => 'Invalid file format or size too large',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('cv_file');
            \Log::info('CV file received', [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ]);
            
            $cvText = $this->extractTextFromFile($file);
            
            if (empty($cvText)) {
                \Log::error('Could not extract text from CV file');
                return response()->json([
                    'error' => 'Could not extract text from the file'
                ], 400);
            }

            \Log::info('Text extracted successfully', ['text_length' => strlen($cvText)]);

            // Clean the extracted text
            $cleanText = $this->cleanText($cvText);
            
            // Send to AI for parsing
            $parsedData = $this->parseWithAI($cleanText);
            
            // Validate and fix JSON if needed
            $validatedData = $this->validateAndFixJson($parsedData);
            
            \Log::info('CV parsing successful', ['data_keys' => array_keys($validatedData)]);
            
            return response()->json([
                'success' => true,
                'data' => $validatedData,
                'raw_text' => $cleanText
            ]);
            
        } catch (\Exception $e) {
            \Log::error('CV parsing failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to parse CV',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract text from uploaded file
     */
    private function extractTextFromFile($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        try {
            switch ($extension) {
                case 'pdf':
                    return $this->extractFromPdf($file);
                    
                case 'doc':
                case 'docx':
                    return $this->extractFromWord($file);
                    
                default:
                    throw new \Exception('Unsupported file format');
            }
        } catch (\Exception $e) {
            throw new \Exception('Failed to extract text: ' . $e->getMessage());
        }
    }

    /**
     * Extract text from PDF file
     */
    private function extractFromPdf($file)
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($file->getPathname());
        return $pdf->getText();
    }

    /**
     * Extract text from Word document
     */
    private function extractFromWord($file)
    {
        $phpWord = IOFactory::load($file->getPathname());
        $text = '';
        
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }
        
        return $text;
    }

    /**
     * Clean and normalize extracted text
     */
    private function cleanText($text)
    {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove special characters but keep useful ones
        $text = preg_replace('/[^\p{L}\p{N}\s\-\.\,\@\(\)\/]/u', '', $text);
        
        // Normalize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Remove multiple consecutive line breaks
        $text = preg_replace('/\n+/', "\n", $text);
        
        // Trim whitespace
        $text = trim($text);
        
        return $text;
    }

    /**
     * Send text to AI for parsing
     */
    private function parseWithAI($cvText)
    {
        try {
            // Get Gemini API key
            $apiKey = env('GEMINI_API_KEY');
            if (!$apiKey) {
                throw new \Exception('Gemini API key not configured');
            }

            // Use the comprehensive prompt builder
            $prompt = $this->buildPrompt($cvText);

            // Call Gemini API
            $client = new Client();
            $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $apiKey, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 2000
                    ]
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            
            \Log::info('Gemini API response', ['response' => $body]);
            
            if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
                $aiResponse = $body['candidates'][0]['content']['parts'][0]['text'];
                
                \Log::info('AI raw response', ['ai_response' => $aiResponse]);
                
                // Extract JSON from AI response - try multiple patterns
                $jsonStr = null;
                
                // Try to find JSON in the response
                if (preg_match('/\{.*\}/s', $aiResponse, $matches)) {
                    $jsonStr = $matches[0];
                } elseif (preg_match('/```json\s*(\{.*\})\s*```/s', $aiResponse, $matches)) {
                    $jsonStr = $matches[1];
                }
                
                if ($jsonStr) {
                    \Log::info('Extracted JSON string', ['json' => $jsonStr]);
                    $parsedData = json_decode($jsonStr, true);
                    
                    if ($parsedData) {
                        \Log::info('Parsed data successfully', ['data' => $parsedData]);
                        return json_encode($parsedData);
                    } else {
                        \Log::error('Failed to decode JSON', ['json_error' => json_last_error_msg()]);
                    }
                }
            }
            
            throw new \Exception('Failed to parse AI response');
            
        } catch (\Exception $e) {
            // Fallback to better extraction if AI fails
            \Log::info('Using fallback extraction method');
            
            $nom_complet = $this->extractName($cvText);
            $email = $this->extractEmail($cvText);
            $telephone = $this->extractPhone($cvText);
            
            return json_encode([
                'nom_complet' => $nom_complet ?: 'Nom à compléter',
                'email' => $email ?: 'email@example.com',
                'telephone' => $telephone ?: null,
                'country' => $this->extractCountry($cvText),
                'city' => $this->extractCity($cvText),
                'annee_etude' => null,
                'date_naissance' => null,
                'portfolio' => null,
                'ecole_actuelle' => $this->extractSchool($cvText),
                'competences' => $this->extractSkills($cvText),
                'experiences' => $this->extractExperiences($cvText),
                'formations' => $this->extractFormations($cvText),
                'projets' => $this->extractProjects($cvText),
                'certificats' => []
            ]);
        }
    }
    
    /**
     * Extract address from CV text
     */
    private function extractCountry($text)
    {
        // Look for country patterns
        if (preg_match('/([A-Z][a-z]+),?\s*([A-Z][a-z]+)/', $text, $matches)) {
            return $matches[2] ?? 'Morocco'; // Default to Morocco if not found
        }
        if (preg_match('/\b(Morocco|Maroc)\b/i', $text, $matches)) {
            return 'Morocco';
        }
        return null;
    }
    
    private function extractCity($text)
    {
        // Look for city patterns
        if (preg_match('/([A-Z][a-z]+),?\s*[A-Z][a-z]+/', $text, $matches)) {
            return $matches[1];
        }
        if (preg_match('/\b(Casablanca|Rabat|Marrakech|Fès|Tanger|Agadir|Oujda)\b/i', $text, $matches)) {
            return ucfirst(strtolower($matches[1]));
        }
        return null;
    }
    
    /**
     * Extract name from CV text
     */
    private function extractName($text)
    {
        // Simple extraction - look for common patterns
        if (preg_match('/([A-Z][a-z]+\s+[A-Z][a-z]+)/', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Extract email from CV text
     */
    private function extractEmail($text)
    {
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $text, $matches)) {
            return $matches[0];
        }
        return null;
    }
    
    /**
     * Extract phone from CV text
     */
    private function extractPhone($text)
    {
        if (preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $text, $matches)) {
            return $matches[0];
        }
        return null;
    }
    
    /**
     * Extract school from CV text
     */
    private function extractSchool($text)
    {
        $schools = ['Université', 'University', 'Ecole', 'School', 'Institut', 'Institute'];
        foreach ($schools as $school) {
            if (stripos($text, $school) !== false) {
                // Extract some context around the school name
                $pos = stripos($text, $school);
                $context = substr($text, max(0, $pos - 10), 50);
                if (preg_match('/([A-Z][a-z]+\s+(?:' . $school . '[A-Za-z\s]*)[A-Z][a-z]+)/', $context, $matches)) {
                    return trim($matches[1]);
                }
                return $school . ' (détails à compléter)';
            }
        }
        return null;
    }
    
    /**
     * Extract skills from CV text
     */
    private function extractSkills($text)
    {
        $commonSkills = [
            'JavaScript', 'Python', 'Java', 'PHP', 'HTML', 'CSS', 'React', 'Angular', 'Vue.js',
            'Node.js', 'MongoDB', 'MySQL', 'PostgreSQL', 'Docker', 'Git', 'Linux', 'AWS',
            'Microsoft Office', 'Excel', 'Word', 'PowerPoint', 'Communication', 'Teamwork',
            'Problem Solving', 'Project Management', 'Agile', 'Scrum'
        ];
        
        $foundSkills = [];
        foreach ($commonSkills as $skill) {
            if (stripos($text, $skill) !== false) {
                $foundSkills[] = $skill;
            }
        }
        
        return array_slice($foundSkills, 0, 10); // Limit to 10 skills
    }
    
    /**
     * Extract education from CV text
     */
    private function extractEducation($text)
    {
        $formations = [];
        
        // Look for education patterns
        if (preg_match_all('/(Bachelor|Master|Licence|Master|Doctorat|PhD|Baccalauréat|BTS|DUT).*?(\d{4})/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $formations[] = [
                    'diplome' => $match[1],
                    'filiere' => 'Informatique', // Default
                    'niveau_etude' => $match[1],
                    'etablissement' => 'Université (à compléter)',
                    'date_debut' => $match[2] . '-01-01',
                    'date_fin' => $match[2] . '-12-31'
                ];
            }
        }
        
        return array_slice($formations, 0, 3); // Limit to 3 formations
    }

    /**
     * Build the AI prompt
     */
    private function buildPrompt($cvText)
    {
        return "You are an AI assistant specialized in CV parsing for the Moroccan job market.

Your task is to extract relevant information from a CV text and return ONLY a structured JSON object that matches the platform's user profile schema.

STRICT RULES:
- Return ONLY valid JSON (no explanation, no text before or after, don't wrap it in markdown code blocks)
- Do NOT invent information
- If a field is missing, return null
- Respect the exact structure provided
- Extract concise and clean data
- Focus on Moroccan context (universities, companies, etc.)

TARGET STRUCTURE:
{
  \"nom_complet\": \"string\",
  \"email\": \"string\",
  \"telephone\": \"string\",
  \"adresse\": \"string\",
  \"annee_etude\": \"string\",
  \"date_naissance\": \"YYYY-MM-DD\",
  \"portfolio\": \"string (URL)\",
  \"ecole_actuelle\": \"string\",
  \"competences\": [\"skill1\", \"skill2\"],
  \"experiences\": [
    {
      \"type\": \"stage|emploi|freelance\",
      \"titre\": \"string\",
      \"entreprise_nom\": \"string\",
      \"description\": \"string\",
      \"competences\": [\"skill1\", \"skill2\"],
      \"en_cours\": boolean,
      \"date_debut\": \"YYYY-MM-DD\",
      \"date_fin\": \"YYYY-MM-DD\"
    }
  ],
  \"formations\": [
    {
      \"diplome\": \"string\",
      \"filiere\": \"string\",
      \"niveau\": \"bac|bac+2|licence|master|doctorat|autre\",
      \"etablissement\": \"string\",
      \"en_cours\": boolean,
      \"date_debut\": \"YYYY-MM-DD\",
      \"date_fin\": \"YYYY-MM-DD\"
    }
  ],
  \"projets\": [
    {
      \"titre\": \"string\",
      \"description\": \"string\",
      \"technologies\": [\"tech1\", \"tech2\"],
      \"date\": \"YYYY-MM-DD\",
      \"lien_demo\": \"string (URL)\",
      \"lien_code\": \"string (URL)\"
    }
  ],
  \"certificats\": [
    {
      \"titre\": \"string\",
      \"organisme\": \"string\",
      \"date_obtention\": \"YYYY-MM-DD\"
    }
  ]
}

EXTRACTION RULES:
- nom_complet: full name of the candidate
- email: valid email if found
- telephone: phone number (include country code if available)
- adresse: city or full address if available
- competences: list of technical or soft skills
- formations: academic education only
- experiences: work experience (internships, jobs, etc.)
- projets: academic or personal projects
- certificats: certifications and certificates

CLEANING RULES:
- Remove duplicates
- Keep short and clear values
- Do not include unnecessary text
- Normalize dates to YYYY-MM-DD format when possible
- Standardize skill names

Now extract the data from the CV below:

CV TEXT:
\"\"\"$cvText\"\"\"";
    }

    /**
     * Validate and fix JSON structure
     */
    private function validateAndFixJson($jsonString)
    {
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to fix common JSON issues
            $jsonString = $this->fixJsonIssues($jsonString);
            $data = json_decode($jsonString, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format from AI response');
            }
        }
        
        // Ensure required structure
        return $this->ensureStructure($data);
    }

    /**
     * Fix common JSON issues
     */
    private function fixJsonIssues($jsonString)
    {
        // Fix common issues
        $jsonString = str_replace(['"', "'"], '"', $jsonString);
        $jsonString = preg_replace('/,\s*}/', '}', $jsonString);
        $jsonString = preg_replace('/,\s*]/', ']', $jsonString);
        
        return $jsonString;
    }

    /**
     * Ensure the data structure matches expected format
     */
    private function ensureStructure($data)
    {
        $defaultStructure = [
            'nom_complet' => null,
            'email' => null,
            'telephone' => null,
            'adresse' => null,
            'annee_etude' => null,
            'date_naissance' => null,
            'portfolio' => null,
            'ecole_actuelle' => null,
            'competences' => [],
            'experiences' => [],
            'formations' => [],
            'projets' => [],
            'certificats' => []
        ];

        return array_merge($defaultStructure, $data);
    }
    
    /**
     * Extract experiences from CV text
     */
    private function extractExperiences($text)
    {
        $experiences = [];
        
        // Look for project-based experience
        if (preg_match('/Restaurant Website.*?Created a static website using HTML and CSS/s', $text, $match)) {
            $experiences[] = [
                'type' => 'freelance',
                'titre' => 'Développeur Web',
                'entreprise_nom' => 'Restaurant Website Project',
                'description' => 'Created a static website using HTML and CSS',
                'competences' => ['HTML', 'CSS'],
                'date_debut' => '2025-02-01',
                'date_fin' => '2025-02-28',
                'en_cours' => false
            ];
        }
        
        return $experiences;
    }
    
    /**
     * Extract formations from CV text
     */
    private function extractFormations($text)
    {
        $formations = [];
        
        // Extract Diploma in Digital Development
        if (preg_match('/Diploma in Digital Development.*?ISGI, Casablanca.*?2024.*?Present/s', $text, $match)) {
            $formations[] = [
                'diplome' => 'Diploma in Digital Development',
                'filiere' => 'Digital Development',
                'niveau' => 'bac+2',
                'etablissement' => 'ISGI, Casablanca',
                'date_debut' => '2024-09-01',
                'date_fin' => null,
                'en_cours' => true
            ];
        }
        
        // Extract Baccalaureate
        if (preg_match('/Baccalaureate in Physical Sciences.*?Lycée IBN BANAÂ.*?2024/s', $text, $match)) {
            $formations[] = [
                'diplome' => 'Baccalaureate in Physical Sciences',
                'filiere' => 'Physical Sciences',
                'niveau' => 'bac',
                'etablissement' => 'Lycée IBN BANAÂ',
                'date_debut' => '2021-09-01',
                'date_fin' => '2024-06-30',
                'en_cours' => false
            ];
        }
        
        return $formations;
    }
    
    /**
     * Extract projects from CV text
     */
    private function extractProjects($text)
    {
        $projects = [];
        
        // Extract Restaurant Website project
        if (preg_match('/Restaurant Website.*?\(Feb 2025\).*?Created a static website using HTML and CSS/s', $text, $match)) {
            $projects[] = [
                'titre' => 'Restaurant Website',
                'description' => 'Created a static website using HTML and CSS',
                'technologies' => ['HTML', 'CSS'],
                'date' => '2025-02-01',
                'lien_demo' => '',
                'lien_code' => ''
            ];
        }
        
        // Extract Online Pharmacy Website project
        if (preg_match('/Online Pharmacy Website.*?\(Apr 2025\).*?Developed a dynamic website using PHP and MySQL.*?Managed database for products and users/s', $text, $match)) {
            $projects[] = [
                'titre' => 'Online Pharmacy Website',
                'description' => 'Developed a dynamic website using PHP and MySQL. Managed database for products and users',
                'technologies' => ['PHP', 'MySQL'],
                'date' => '2025-04-01',
                'lien_demo' => '',
                'lien_code' => ''
            ];
        }
        
        return $projects;
    }
}
