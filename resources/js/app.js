

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// ---- 生年月日: カレンダー選択 + 直接入力 ----
import flatpickr from 'flatpickr';
import { Japanese } from 'flatpickr/dist/l10n/ja.js';
import 'flatpickr/dist/flatpickr.min.css';

const birthdayInput = document.getElementById('birthday');
if (birthdayInput) {
    flatpickr(birthdayInput, {
        locale: Japanese,
        dateFormat: 'Y/m/d',
        allowInput: true,
        maxDate: 'today',
        // 打ち込んだ文字を日付として読み取る（19850202 / 1985/2/2 / 1985-2-2 など）
        parseDate: (str) => {
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
        },
    });
}
