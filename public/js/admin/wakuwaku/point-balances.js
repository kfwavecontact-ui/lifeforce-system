/**
 * わくわく ＞ ポイント ＞ ポイント残高 専用JavaScript。
 * 後続画面が未実装のボタンを誤操作した際に、現在の状態を明示する。
 */
document.addEventListener('DOMContentLoaded', () => {
    const toast = document.getElementById('pointBalanceToast');
    let timer = null;

    document.querySelectorAll('[data-coming-soon]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!toast) return;
            const screenName = button.dataset.comingSoon || 'この画面';
            toast.textContent = `${screenName}は次の開発工程で実装します。`;
            toast.classList.add('is-visible');
            window.clearTimeout(timer);
            timer = window.setTimeout(() => toast.classList.remove('is-visible'), 2600);
        });
    });
});
