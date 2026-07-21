/**
 * ポイント調整画面専用JavaScript。
 * 調整後残高のプレビュー、理由文字数、実行確認、二重送信防止を担当する。
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('pointAdjustmentForm');
    if (!form) return;

    const currentElement = document.getElementById('currentPointValue');
    const pointsInput = document.getElementById('adjustmentPoints');
    const reasonInput = document.getElementById('adjustmentReason');
    const reasonCount = document.getElementById('reasonCount');
    const preview = document.getElementById('adjustedPointPreview');
    const previewError = document.getElementById('adjustmentPreviewError');
    const submitButton = document.getElementById('pointAdjustmentSubmit');
    const typeInputs = form.querySelectorAll('input[name="adjustment_type"]');
    const currentPoints = Number(currentElement?.dataset.currentPoints || 0);

    const numberFormat = new Intl.NumberFormat('ja-JP');

    const selectedType = () => form.querySelector('input[name="adjustment_type"]:checked')?.value || 'add';

    const updatePreview = () => {
        const points = Math.max(0, Number(pointsInput?.value || 0));
        const after = selectedType() === 'subtract' ? currentPoints - points : currentPoints + points;
        const isInvalid = selectedType() === 'subtract' && after < 0;

        if (preview) {
            preview.textContent = `${numberFormat.format(after)} pt`;
            preview.classList.toggle('is-negative', after < 0);
        }
        if (previewError) previewError.hidden = !isInvalid;
        if (submitButton) submitButton.disabled = isInvalid;
    };

    const updateReasonCount = () => {
        if (reasonCount) reasonCount.textContent = String(reasonInput?.value.length || 0);
    };

    pointsInput?.addEventListener('input', updatePreview);
    typeInputs.forEach((input) => input.addEventListener('change', updatePreview));
    reasonInput?.addEventListener('input', updateReasonCount);

    form.addEventListener('submit', (event) => {
        updatePreview();
        if (submitButton?.disabled) {
            event.preventDefault();
            return;
        }

        const points = Number(pointsInput?.value || 0);
        const typeLabel = selectedType() === 'add' ? '加算' : '減算';
        const message = `${numberFormat.format(points)}ptを${typeLabel}します。よろしいですか？`;

        if (!window.confirm(message)) {
            event.preventDefault();
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = '処理中...';
        }
    });

    updatePreview();
    updateReasonCount();
});
