<div class="admin-form-grid">

    <div class="admin-form-group">
        <label class="admin-label">
            通知コード
            <span class="required">*</span>
        </label>

        <input
            type="text"
            name="code"
            class="admin-input"
            value="{{ old('code', $notificationMaster?->code) }}"
            maxlength="100"
            required
        >

        @error('code')
            <div class="admin-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="admin-form-group">
        <label class="admin-label">
            通知名
            <span class="required">*</span>
        </label>

        <input
            type="text"
            name="name"
            class="admin-input"
            value="{{ old('name', $notificationMaster?->name) }}"
            maxlength="100"
            required
        >

        @error('name')
            <div class="admin-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="admin-form-group">
        <label class="admin-label">
            カテゴリ
            <span class="required">*</span>
        </label>

        <select
            name="category"
            class="admin-select"
            required
        >
            <option value="">選択してください</option>

            @foreach([
                '授業',
                'ルーティン',
                '計画学習',
                'チャレンジ',
                'バッジ',
                '称号',
                'イベント',
                '会計',
                '対面フォロー',
                'システム'
            ] as $category)

                <option
                    value="{{ $category }}"
                    @selected(old('category', $notificationMaster?->category) === $category)
                >
                    {{ $category }}
                </option>

            @endforeach

        </select>

        @error('category')
            <div class="admin-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="admin-form-group">
        <label class="admin-label">
            表示順
            <span class="required">*</span>
        </label>

        <input
            type="number"
            name="sort_order"
            class="admin-input"
            value="{{ old('sort_order', $notificationMaster?->sort_order ?? 0) }}"
            required
        >

        @error('sort_order')
            <div class="admin-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="admin-form-group admin-form-group-full">
        <label class="admin-label">
            説明
        </label>

        <textarea
            name="description"
            rows="4"
            class="admin-textarea"
        >{{ old('description', $notificationMaster?->description) }}</textarea>

        @error('description')
            <div class="admin-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="admin-form-group">
        <label class="admin-label">
            初期状態
        </label>

        <label class="admin-checkbox">
            <input
                type="checkbox"
                name="default_enabled"
                value="1"
                @checked(old('default_enabled', $notificationMaster?->default_enabled ?? true))
            >
            ON
        </label>
    </div>

    <div class="admin-form-group">
        <label class="admin-label">
            有効状態
        </label>

        <label class="admin-checkbox">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                @checked(old('is_active', $notificationMaster?->is_active ?? true))
            >
            有効
        </label>
    </div>

</div>