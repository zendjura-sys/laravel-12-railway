using System;
using System.Globalization;

namespace Tycoon
{
    // Форматирование больших чисел: 1.23K, 4.5M, 7.89B ...
    public static class Fmt
    {
        static readonly string[] Suffix = { "", "K", "M", "B", "T", "Qa", "Qi", "Sx", "Sp", "Oc", "No", "Dc" };
        static readonly CultureInfo Inv = CultureInfo.InvariantCulture;

        public static string Num(double v)
        {
            if (double.IsNaN(v) || double.IsInfinity(v)) return "∞";
            var sign = v < 0 ? "-" : "";
            v = Math.Abs(v);
            if (v < 1000)
            {
                if (v < 10 && v != Math.Floor(v)) return sign + v.ToString("0.##", Inv);
                if (v < 100 && v != Math.Floor(v)) return sign + v.ToString("0.#", Inv);
                return sign + Math.Floor(v).ToString("0", Inv);
            }
            int i = 0;
            while (v >= 1000 && i < Suffix.Length - 1) { v /= 1000; i++; }
            if (v >= 1000) return sign + v.ToString("0.##E+0", Inv) + Suffix[i];
            var fmt = v < 10 ? "0.00" : v < 100 ? "0.0" : "0";
            return sign + v.ToString(fmt, Inv) + Suffix[i];
        }

        public static string Money(double v) { return v < 0 ? "-$" + Num(-v) : "$" + Num(v); }

        public static string Pct(double v, bool plus = true)
        {
            var s = (v * 100).ToString(Math.Abs(v) < 0.1 ? "0.0" : "0", Inv) + "%";
            return plus && v > 0 ? "+" + s : s;
        }

        public static string Time(double sec)
        {
            sec = Math.Max(0, Math.Ceiling(sec));
            var h = (int)(sec / 3600);
            var m = (int)(sec % 3600 / 60);
            var s = (int)(sec % 60);
            if (h > 0) return h + "ч " + m + "м";
            if (m > 0) return m + "м " + s.ToString("00") + "с";
            return s + "с";
        }
    }
}
