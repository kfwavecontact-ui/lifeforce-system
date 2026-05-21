<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザー一覧</title>

    <style>
        body {
            font-family: sans-serif;
            padding: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 10px;
        }

        th {
            background: #f5f5f5;
        }
    </style>
</head>
<body>

<h1>ユーザー一覧</h1>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>名前</th>
            <th>メール</th>
            <th>契約コース</th>
            <th>プラン</th>
            <th>料金</th>
            <th>状態</th>
        </tr>
    </thead>

    <tbody>

    @foreach($users as $user)

        @php
            $contract = $user->courseContracts->first();
        @endphp

        <tr>
            <td>{{ $user->id }}</td>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>

            <td>
                {{ optional($contract?->coursePrice?->course)->name }}
            </td>

            <td>
                {{ optional($contract?->coursePrice)->plan_type }}
            </td>

            <td>
                {{ optional($contract?->coursePrice)->price }}
                円
            </td>

            <td>
                {{ $contract?->status }}
            </td>
        </tr>

    @endforeach

    </tbody>
</table>

</body>
</html>
