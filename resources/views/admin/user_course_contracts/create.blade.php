<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>契約追加</title>
</head>
<body>

<h1>契約追加</h1>

@if ($errors->any())
    <div style="color:red;">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.user-course-contracts.store') }}">
    @csrf

    <div>
        <label>ユーザー</label>
        <select name="user_id" required>
            <option value="">選択してください</option>

            @foreach ($users as $user)
                <option value="{{ $user->id }}">
                    {{ $user->id }} : {{ $user->name }}
                </option>
            @endforeach
        </select>
    </div>

    <br>

    <div>
        <label>コース / プラン</label>

        <select name="course_price_id" required>
            <option value="">選択してください</option>

            @foreach ($coursePrices as $coursePrice)
                <option value="{{ $coursePrice->id }}">
                    {{ $coursePrice->course->name }}
                    /
                    {{ $coursePrice->plan_type }}
                    /
                    ¥{{ number_format($coursePrice->price) }}
                </option>
            @endforeach
        </select>
    </div>

    <br>

    <div>
        <label>開始日</label>

        <input
            type="date"
            name="start_date"
            value="{{ date('Y-m-d') }}"
            required
        >
    </div>

    <br>

    <button type="submit">
        保存
    </button>

</form>

</body>
</html>
