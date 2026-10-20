

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// ---- 生年月日: カレンダー選択 + 直接入力 ----
import flatpickr from 'flatpickr';
import { Japanese } from 'flatpickr/dist/l10n/ja.js';
import 'flatpickr/dist/flatpickr.min.css';

// 送る値（サーバーに渡す値）は Y-m-d、画面に見せる値は Y/m/d にする（altInput）。
// 送る値を Y/m/d にすると、サーバー側の入力チェック（Y-m-d）で止まってしまうため。
const BIRTHDAY_MESSAGE = '生年月日は、1990/01/01 のように入力してください。';

// 打ち込んだ文字を日付として読み取る（19850202 / 1985/2/2 / 1985-2-2 / 全角 など）。読めなければ undefined
const parseBirthday = (str) => {
    const s = String(str)
        .trim()
        .replace(/[０-９]/g, (c) => String.fromCharCode(c.charCodeAt(0) - 0xfee0));
    const m = s.match(/^(\d{4})\D?(\d{1,2})\D?(\d{1,2})\D*$/);
    if (!m) return undefined;
    const [y, mo, d] = [Number(m[1]), Number(m[2]), Number(m[3])];
    const date = new Date(y, mo - 1, d);
    if (date.getFullYear() !== y || date.getMonth() !== mo - 1 || date.getDate() !== d) {
        return undefined; // 存在しない日付（例: 2月30日）
    }
    return date;
};

document.querySelectorAll('input[data-birthday]').forEach((birthdayInput) => {
    const fp = flatpickr(birthdayInput, {
        locale: Japanese,
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'Y/m/d',
        allowInput: true,
        maxDate: 'today',
        position: 'above', // 下に開くと、送信ボタンに重なって押せなくなるため
        parseDate: parseBirthday,
    });

    const alt = fp.altInput;
    const message = document.createElement('p');
    message.className = 'mt-1 text-sm text-red-600';
    message.hidden = true;
    alt.insertAdjacentElement('afterend', message);

    // 打ち込んだ値を覚えておく（読めない値は、カレンダー部品が欄を空にしてしまうため）
    let typed = alt.value;
    alt.addEventListener('input', () => {
        typed = alt.value;
        message.hidden = true;
    });

    // 打ち込んだ値を確定する。読めない値は消さずに残して、その場でエラーを出す
    const commit = () => {
        const text = typed.trim();
        if (text === '') {
            fp.clear();
            message.hidden = true;
            return true;
        }
        const date = parseBirthday(text);
        if (date) {
            fp.setDate(date, true);
            typed = alt.value;
            message.hidden = true;
            return true;
        }
        birthdayInput.value = '';
        alt.value = text;
        message.textContent = BIRTHDAY_MESSAGE;
        message.hidden = false;
        return false;
    };

    alt.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === 'Tab') {
            if (e.key === 'Enter') e.preventDefault(); // Enter で送信せず、日付だけ確定する
            commit();
            fp.close();
        }
    });
    alt.addEventListener('blur', () => setTimeout(commit, 0));
    fp.config.onChange.push(() => {
        typed = alt.value; // カレンダーで選んだとき
        message.hidden = true;
    });

    // 読めない日付のまま送信しない
    birthdayInput.form?.addEventListener('submit', (e) => {
        if (!commit()) {
            e.preventDefault();
            fp.close();
            alt.focus();
        }
    });
});
