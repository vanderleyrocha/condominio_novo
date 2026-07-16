<!DOCTYPE html>
<html lang="pt_BR">

<head>
    <meta charset="UTF-8">
    <title>@yield('title')</title>

    {{-- CSS print básico embutido (dompdf não suporta Tailwind) --}}
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            color: #000;
        }

        h1, h2, h3, h4, h5, p {
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1mm 2mm;
            vertical-align: middle;
        }

        thead th {
            font-weight: bold;
        }

        tfoot th, tfoot td {
            font-weight: bold;
        }

        .table-bordered th, .table-bordered td, .bordered {
            border: 1px solid #000;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-bold, .font-weight-bold { font-weight: bold; }
        .font-normal { font-weight: normal; }
        .text-danger { color: #dc3545; }
        .bg-light { background-color: #f8f9fa; }

        .mb-2 { margin-bottom: 3pt; }
        .mb-3 { margin-bottom: 6pt; }
        .mt-4 { margin-top: 10pt; }

        .pmhMiddleLeft { text-align: left; vertical-align: middle; }
        .pmhMiddleRight { text-align: right; vertical-align: middle; }
        .pmhMiddleCenter { text-align: center; vertical-align: middle; }
    </style>

    @yield('styles')
</head>

<body>
@yield('content')
</body>

</html>
