<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ma belle image</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Styles globaux */
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #f9f9f9, #e0f7fa);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Container de l'image */
        .image-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 600px;
            width: 100%;
            transition: transform 0.3s;
        }

        .image-container:hover {
            transform: scale(1.05);
        }

        /* L'image */
        .image-container img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
        }

        /* Texte sous l'image */
        .image-container h1 {
            margin: 15px 0 0 0;
            color: #00796b;
            font-size: 24px;
        }

        .image-container p {
            margin: 10px 0 0 0;
            color: #555;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="image-container">
        <img src="{{ asset('images/mon_image.gif') }}" alt="Belle image">
        <h1>APPROUVED</h1>
        <p>Knuckle approuved this.</p>
    </div>
</body>
</html>
