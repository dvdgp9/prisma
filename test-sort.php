<!DOCTYPE html>
<html>

<head>
    <title>Test Sort - Prisma</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
        }

        .test {
            margin: 20px 0;
            padding: 10px;
            background: #f0f0f0;
        }

        .success {
            background: #d4edda;
        }

        .error {
            background: #f8d7da;
        }
    </style>
</head>

<body>
    <h1>Test de Ordenamiento - Prisma</h1>

    <div id="results"></div>

    <script>
        const resultsDiv = document.getElementById('results');

        async function testSort(sortValue, label) {
            const url = `/api/requests.php?sort=${sortValue}`;

            try {
                const response = await fetch(url);
                const data = await response.json();

                const div = document.createElement('div');
                div.className = 'test success';
                div.innerHTML = `
                    <h3>✅ ${label} (sort=${sortValue})</h3>
                    <p><strong>URL:</strong> ${url}</p>
                    <p><strong>Requests encontrados:</strong> ${data.data.length}</p>
                    <p><strong>Orden:</strong></p>
                    <ul>
                        ${data.data.slice(0, 5).map(r => `
                            <li>${r.title} - 
                                Priority: ${r.priority}, 
                                Votes: ${r.vote_count}, 
                                Date: ${r.created_at}
                            </li>
                        `).join('')}
                    </ul>
                `;
                resultsDiv.appendChild(div);
            } catch (error) {
                const div = document.createElement('div');
                div.className = 'test error';
                div.innerHTML = `
                    <h3>❌ ${label} (sort=${sortValue})</h3>
                    <p><strong>Error:</strong> ${error.message}</p>
                `;
                resultsDiv.appendChild(div);
            }
        }

        // Run tests
        (async () => {
            await testSort('date', 'Más reciente (por defecto)');
            await testSort('date_asc', 'Más antigua');
            await testSort('priority', 'Por prioridad');
            await testSort('votes', 'Más votadas');
        })();
    </script>
</body>

</html>