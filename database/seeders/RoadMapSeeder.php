<?php

namespace Database\Seeders;

use App\Models\Node;
use App\Models\RoadMap;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoadMapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roadmaps = [
            // 1) Backend Development
            [
                'title' => 'Backend Development',
                'description' => 'A roadmap for becoming a modern backend developer, focusing on HTTP, databases, APIs, and deployment.',
                'nodes' => [
                    [
                        'step_number' => 1,
                        'title' => 'Understand how the web works (HTTP, clients, servers)',
                        'url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP',
                    ],
                    [
                        'step_number' => 2,
                        'title' => 'Learn a backend language (e.g. PHP, Python, Node.js)',
                        'url' => 'https://roadmap.sh/backend', // curated backend roadmap 
                    ],
                    [
                        'step_number' => 3,
                        'title' => 'Learn relational databases and SQL',
                        'url' => 'https://www.postgresql.org/docs/current/tutorial.html',
                    ],
                    [
                        'step_number' => 4,
                        'title' => 'Learn RESTful API design',
                        'url' => 'https://restfulapi.net/',
                    ],
                    [
                        'step_number' => 5,
                        'title' => 'Learn authentication and authorization (sessions, tokens, JWT, OAuth)',
                        'url' => 'https://auth0.com/intro-to-iam',
                    ],
                    [
                        'step_number' => 6,
                        'title' => 'Learn caching (HTTP caching, Redis, CDN basics)',
                        'url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching',
                    ],
                    [
                        'step_number' => 7,
                        'title' => 'Learn testing for backend (unit, integration, API tests)',
                        'url' => 'https://martinfowler.com/articles/practical-test-pyramid.html',
                    ],
                    [
                        'step_number' => 8,
                        'title' => 'Learn deployment and DevOps basics (Linux, Docker, CI/CD)',
                        'url' => 'https://docs.docker.com/get-started/',
                    ],
                ],
            ],

            // 2) Frontend Development
            [
                'title' => 'Frontend Development',
                'description' => 'A roadmap for becoming a modern frontend developer, from HTML/CSS/JS to frameworks and tooling.',
                'nodes' => [
                    [
                        'step_number' => 1,
                        'title' => 'Learn HTML fundamentals',
                        'url' => 'https://developer.mozilla.org/en-US/docs/Learn/HTML',
                    ],
                    [
                        'step_number' => 2,
                        'title' => 'Learn CSS fundamentals (layout, flexbox, grid)',
                        'url' => 'https://developer.mozilla.org/en-US/docs/Learn/CSS',
                    ],
                    [
                        'step_number' => 3,
                        'title' => 'Learn core JavaScript (ES6+)',
                        'url' => 'https://developer.mozilla.org/en-US/docs/Learn/JavaScript',
                    ],
                    [
                        'step_number' => 4,
                        'title' => 'Learn modern frontend tooling (NPM, bundlers, Vite)',
                        'url' => 'https://vitejs.dev/guide/',
                    ],
                    [
                        'step_number' => 5,
                        'title' => 'Learn a frontend framework (React as a common choice)',
                        'url' => 'https://react.dev/learn',
                    ],
                    [
                        'step_number' => 6,
                        'title' => 'Learn TypeScript for safer frontend code',
                        'url' => 'https://www.typescriptlang.org/docs/handbook/intro.html',
                    ],
                    [
                        'step_number' => 7,
                        'title' => 'Learn state management and routing concepts',
                        'url' => 'https://redux.js.org/tutorials/essentials/part-1-overview-concepts',
                    ],
                    [
                        'step_number' => 8,
                        'title' => 'Study a curated frontend roadmap',
                        'url' => 'https://roadmap.sh/frontend', // curated, updated roadmap 
                    ],
                ],
            ],

            // 3) Data Analysis
            [
                'title' => 'Data Analysis',
                'description' => 'A roadmap for learning data analysis using Python, statistics, and visualization tools.',
                'nodes' => [
                    [
                        'step_number' => 1,
                        'title' => 'Learn Python basics',
                        'url' => 'https://docs.python.org/3/tutorial/',
                    ],
                    [
                        'step_number' => 2,
                        'title' => 'Learn data analysis with NumPy and pandas',
                        'url' => 'https://pandas.pydata.org/docs/getting_started/index.html',
                    ],
                    [
                        'step_number' => 3,
                        'title' => 'Learn data visualization (Matplotlib, Seaborn)',
                        'url' => 'https://seaborn.pydata.org/tutorial.html',
                    ],
                    [
                        'step_number' => 4,
                        'title' => 'Learn basic statistics for data analysis',
                        'url' => 'https://www.khanacademy.org/math/statistics-probability',
                    ],
                    [
                        'step_number' => 5,
                        'title' => 'Practice with real datasets (Kaggle)',
                        'url' => 'https://www.kaggle.com/learn',
                    ],
                    [
                        'step_number' => 6,
                        'title' => 'Learn exploratory data analysis (EDA) techniques',
                        'url' => 'https://towardsdatascience.com/exploratory-data-analysis-8fc1cb20fd15',
                    ],
                    [
                        'step_number' => 7,
                        'title' => 'Learn SQL for querying data',
                        'url' => 'https://mode.com/sql-tutorial/',
                    ],
                    [
                        'step_number' => 8,
                        'title' => 'Take a structured data analysis course',
                        'url' => 'https://www.coursera.org/specializations/google-data-analytics',
                    ],
                ],
            ],

            // 4) Machine Learning
            [
                'title' => 'Machine Learning',
                'description' => 'A roadmap for learning machine learning fundamentals, libraries, and practical modeling.',
                'nodes' => [
                    [
                        'step_number' => 1,
                        'title' => 'Strengthen Python and math foundations (linear algebra, calculus, probability)',
                        'url' => 'https://www.khanacademy.org/math/linear-algebra',
                    ],
                    [
                        'step_number' => 2,
                        'title' => 'Learn core ML concepts (supervised, unsupervised, overfitting, evaluation)',
                        'url' => 'https://developers.google.com/machine-learning/crash-course',
                    ],
                    [
                        'step_number' => 3,
                        'title' => 'Learn scikit-learn for classical ML',
                        'url' => 'https://scikit-learn.org/stable/tutorial/index.html',
                    ],
                    [
                        'step_number' => 4,
                        'title' => 'Learn data preprocessing and feature engineering',
                        'url' => 'https://scikit-learn.org/stable/modules/preprocessing.html',
                    ],
                    [
                        'step_number' => 5,
                        'title' => 'Learn deep learning basics with PyTorch or TensorFlow',
                        'url' => 'https://pytorch.org/tutorials/beginner/deep_learning_60min_blitz.html',
                    ],
                    [
                        'step_number' => 6,
                        'title' => 'Work on ML projects with real datasets',
                        'url' => 'https://www.kaggle.com/competitions',
                    ],
                    [
                        'step_number' => 7,
                        'title' => 'Learn model deployment basics (APIs, containers)',
                        'url' => 'https://www.coursera.org/learn/mlops-deployment',
                    ],
                    [
                        'step_number' => 8,
                        'title' => 'Follow a structured ML specialization',
                        'url' => 'https://www.coursera.org/specializations/machine-learning-introduction',
                    ],
                ],
            ],

            // 5) Cyber Security
            [
                'title' => 'Cyber Security',
                'description' => 'A roadmap for learning core cybersecurity concepts, networking, and practical defense skills.',
                'nodes' => [
                    [
                        'step_number' => 1,
                        'title' => 'Learn computer networking fundamentals',
                        'url' => 'https://www.cloudflare.com/learning/network-layer/what-is-a-computer-network/',
                    ],
                    [
                        'step_number' => 2,
                        'title' => 'Learn basic Linux and command line usage',
                        'url' => 'https://linuxjourney.com/',
                    ],
                    [
                        'step_number' => 3,
                        'title' => 'Study cybersecurity fundamentals',
                        'url' => 'https://www.coursera.org/specializations/ibm-cybersecurity-analyst',
                    ],
                    [
                        'step_number' => 4,
                        'title' => 'Learn about web security and OWASP Top 10',
                        'url' => 'https://owasp.org/www-project-top-ten/',
                    ],
                    [
                        'step_number' => 5,
                        'title' => 'Learn about cryptography basics',
                        'url' => 'https://cryptobook.nakov.com/',
                    ],
                    [
                        'step_number' => 6,
                        'title' => 'Practice on safe, legal cyber ranges',
                        'url' => 'https://tryhackme.com/',
                    ],
                    [
                        'step_number' => 7,
                        'title' => 'Learn security monitoring and incident response basics',
                        'url' => 'https://www.splunk.com/en_us/resources/what-is-security-operations.html',
                    ],
                    [
                        'step_number' => 8,
                        'title' => 'Follow a structured cybersecurity learning path',
                        'url' => 'https://www.cybrary.it/catalog/career-paths/cybersecurity-engineer/',
                    ],
                ],
            ],

            // 6) Mobile Development
            [
                'title' => 'Mobile Development',
                'description' => 'A roadmap for learning native and cross-platform mobile app development.',
                'nodes' => [
                    [
                        'step_number' => 1,
                        'title' => 'Understand mobile app architectures and platforms (iOS, Android, cross-platform)',
                        'url' => 'https://developer.android.com/guide',
                    ],
                    [
                        'step_number' => 2,
                        'title' => 'Learn Android development basics (Kotlin preferred)',
                        'url' => 'https://developer.android.com/courses/android-basics-kotlin/course',
                    ],
                    [
                        'step_number' => 3,
                        'title' => 'Learn iOS development basics (Swift, Xcode)',
                        'url' => 'https://developer.apple.com/tutorials/app-dev-training',
                    ],
                    [
                        'step_number' => 4,
                        'title' => 'Learn cross-platform development with Flutter',
                        'url' => 'https://docs.flutter.dev/get-started/codelab',
                    ],
                    [
                        'step_number' => 5,
                        'title' => 'Learn cross-platform development with React Native',
                        'url' => 'https://reactnative.dev/docs/environment-setup',
                    ],
                    [
                        'step_number' => 6,
                        'title' => 'Learn mobile UI/UX best practices',
                        'url' => 'https://material.io/design',
                    ],
                    [
                        'step_number' => 7,
                        'title' => 'Learn mobile app testing and debugging',
                        'url' => 'https://developer.android.com/studio/debug',
                    ],
                    [
                        'step_number' => 8,
                        'title' => 'Learn publishing apps to app stores',
                        'url' => 'https://developer.android.com/distribute',
                    ],
                ],
            ],
        ];

        foreach ($roadmaps as $roadmapData) {
            $nodes = $roadmapData['nodes'];
            unset($roadmapData['nodes']);

            $roadmap = RoadMap::create($roadmapData);

            foreach ($nodes as $node) {
                Node::create([
                    'road_map_id'   => $roadmap->id,
                    'step_number'  => $node['step_number'],
                    'title'        => $node['title'],
                    'url'          => $node['url'],
                ]);
            }
        }
    }
}
