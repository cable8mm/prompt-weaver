<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <title>Prompt Weaver Workbench</title>
    <style>
        body { font-family: sans-serif; margin: 2rem auto; max-width: 960px; line-height: 1.5; }
        label { display: block; margin: .75rem 0 .25rem; }
        select, input, button { font: inherit; padding: .45rem; }
        pre { background: #f4f4f4; padding: 1rem; overflow: auto; white-space: pre-wrap; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        img { max-width: 100%; border: 1px solid #ddd; }
        .status { padding: .75rem; background: #eef6ff; }
    </style>
</head>
<body>
    <h1>Prompt Weaver Workbench</h1>

    <h2>1. Prompt 생성</h2>
    <form method="post" action="{{ route('workbench.prompt-weaver.prepare') }}">
        @csrf
        <div class="grid">
            <label>Category
                <select name="category">
                    @foreach ($categories as $case)
                        <option value="{{ $case->value }}">{{ $case->value }}</option>
                    @endforeach
                </select>
            </label>
            <label>Format
                <select name="format">
                    @foreach ($formats as $case)
                        <option value="{{ $case->value }}">{{ $case->value }}</option>
                    @endforeach
                </select>
            </label>
            <label>Color mode
                <select name="color_mode">
                    @foreach ($colorModes as $case)
                        <option value="{{ $case->value }}">{{ $case->value }}</option>
                    @endforeach
                </select>
            </label>
            <label>Layout
                <select name="layout">
                    @foreach ($layouts as $case)
                        <option value="{{ $case->value }}">{{ $case->value }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <button type="submit">init + pipe</button>
    </form>

    @if ($generation)
        <hr>
        <div class="status">
            <strong>Status:</strong> {{ $generation->status }}
            <strong>Code:</strong> {{ $generation->code }}
        </div>

        <h2>2. Image prompt</h2>
        <pre>{{ $generation->image_prompt }}</pre>

        <h2>3. 생성 이미지 업로드</h2>
        <form method="post" enctype="multipart/form-data" action="{{ route('workbench.prompt-weaver.upload', $generation) }}">
            @csrf
            <input type="file" name="image" accept="image/png" required>
            <button type="submit">Upload PNG</button>
        </form>

        @if ($generation->image_path)
            <p><img src="{{ route('workbench.prompt-weaver.asset', [$generation, 'image']) }}" alt="Uploaded image"></p>
            <form method="post" action="{{ route('workbench.prompt-weaver.calibrate', $generation) }}">
                @csrf
                <button type="submit">calibrate + preview</button>
            </form>
        @endif

        @if ($generation->preview_path)
            <h2>4. Preview</h2>
            <p><img src="{{ route('workbench.prompt-weaver.asset', [$generation, 'preview']) }}" alt="Calibrated preview"></p>
        @endif
    @endif
</body>
</html>
