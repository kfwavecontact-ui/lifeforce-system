/**
 * ポイント履歴画面専用JavaScript。
 * 日付範囲の入力確認と、未実装機能の案内だけを担当する。
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('pointHistoryFilterForm');
    const toast = document.getElementById('pointHistoryToast');

    const showToast = (message) => {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('is-visible');
        window.setTimeout(() => toast.classList.remove('is-visible'), 2200);
    };

    form?.addEventListener('submit', (event) => {
        const startDate = form.querySelector('[name="start_date"]')?.value;
        const endDate = form.querySelector('[name="end_date"]')?.value;

        if (startDate && endDate && startDate > endDate) {
            event.preventDefault();
            showToast('終了日は開始日以降の日付を指定してください。');
        }
    });

    document.querySelectorAll('[data-coming-soon]').forEach((button) => {
        button.addEventListener('click', () => {
            showToast(`${button.dataset.comingSoon}は次の工程で実装します。`);
        });
    });
});
