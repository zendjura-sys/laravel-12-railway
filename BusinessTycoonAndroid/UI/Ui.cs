using System;
using System.Text;
using Android.Content;
using Android.Graphics;
using Android.Graphics.Drawables;
using Android.Text;
using Android.Text.Style;
using Android.Util;
using Android.Views;
using Android.Widget;

namespace Tycoon.Droid
{
    // Палитра в стиле «индиго-окна + золотые кнопки» (по референсу мобильных окон)
    public static class Pal
    {
        public static readonly Color Bg = Color.ParseColor("#14152A");
        public static readonly Color Panel = Color.ParseColor("#3A3E6C");
        public static readonly Color Header = Color.ParseColor("#30335C");
        public static readonly Color Card = Color.ParseColor("#2D3158");
        public static readonly Color CardAlt = Color.ParseColor("#262A4C");
        public static readonly Color Line = Color.ParseColor("#4A4F82");
        public static readonly Color Text = Color.White;
        public static readonly Color Muted = Color.ParseColor("#AEB2D8");
        public static readonly Color Title = Color.ParseColor("#C9CCEB");
        public static readonly Color Gold = Color.ParseColor("#F2C94C");
        public static readonly Color GoldLip = Color.ParseColor("#B98F2A");
        public static readonly Color Blue = Color.ParseColor("#4B8FE0");
        public static readonly Color BlueLip = Color.ParseColor("#2F66AA");
        public static readonly Color Green = Color.ParseColor("#3DCB7A");
        public static readonly Color GreenLip = Color.ParseColor("#279A57");
        public static readonly Color Red = Color.ParseColor("#E8605A");
        public static readonly Color RedLip = Color.ParseColor("#A93F3B");
        public static readonly Color Btn = Color.ParseColor("#4E5390");
        public static readonly Color BtnLip = Color.ParseColor("#383C6C");
        public static readonly Color BtnOff = Color.ParseColor("#454970");
        public static readonly Color BtnOffLip = Color.ParseColor("#35385A");
        public static readonly Color TextDark = Color.ParseColor("#2A2D52");

        public static Color LipOf(Color c)
        {
            if (c == Gold) return GoldLip;
            if (c == Blue) return BlueLip;
            if (c == Green) return GreenLip;
            if (c == Red) return RedLip;
            if (c == Btn) return BtnLip;
            return Shade(c, 0.7f);
        }

        public static Color Shade(Color c, float k)
        {
            return Color.Argb(c.A, (int)(c.R * k), (int)(c.G * k), (int)(c.B * k));
        }
    }

    // Текст с цветными фрагментами; Key позволяет не перерисовывать TextView без изменений
    public class Rich
    {
        readonly SpannableStringBuilder sb = new SpannableStringBuilder();
        readonly StringBuilder key = new StringBuilder();

        public string Key { get { return key.ToString(); } }

        public Rich T(string s)
        {
            sb.Append(s);
            key.Append(s);
            return this;
        }

        public Rich C(string s, Color c)
        {
            int st = sb.Length();
            sb.Append(s);
            sb.SetSpan(new ForegroundColorSpan(c), st, sb.Length(), SpanTypes.ExclusiveExclusive);
            key.Append('\u0001').Append(c.ToArgb()).Append(s);
            return this;
        }

        public Rich B(string s, Color c)
        {
            int st = sb.Length();
            C(s, c);
            sb.SetSpan(new StyleSpan(TypefaceStyle.Bold), st, sb.Length(), SpanTypes.ExclusiveExclusive);
            key.Append('\u0002');
            return this;
        }

        public Rich Small(string s, Color c)
        {
            int st = sb.Length();
            C(s, c);
            sb.SetSpan(new RelativeSizeSpan(0.78f), st, sb.Length(), SpanTypes.ExclusiveExclusive);
            key.Append('\u0003');
            return this;
        }

        public Rich N() { return T("\n"); }

        // Добавляет другой Rich вместе с цветами
        public Rich R(Rich other)
        {
            sb.Append(other.sb);
            key.Append(other.Key);
            return this;
        }
        public int Length { get { return sb.Length(); } }
        public SpannableStringBuilder Spannable { get { return sb; } }
    }

    // Объёмная кнопка: тёмная «губа» снизу + лицевая часть; при нажатии «проседает»
    public class Btn
    {
        public TextView View;
        Color normal;
        bool enabled = true;
        string lastText;

        public Btn(TextView v, Color c) { View = v; normal = c; Apply(); }

        public void SetColor(Color c)
        {
            if (c == normal) return;
            normal = c;
            Apply();
        }

        public bool Enabled
        {
            set
            {
                if (enabled == value) return;
                enabled = value;
                View.Enabled = value;
                View.SetTextColor(value ? Pal.Text : Pal.Muted);
            }
        }

        public string Text
        {
            set
            {
                if (lastText == value) return;
                lastText = value;
                View.Text = value;
            }
        }

        public void SetRich(Rich r)
        {
            var k = r.Key;
            if (lastText == k) return;
            lastText = k;
            View.TextFormatted = r.Spannable;
        }

        void Apply()
        {
            var states = new StateListDrawable();
            states.AddState(new[] { -Android.Resource.Attribute.StateEnabled }, Ui.Layered(Pal.BtnOff, Pal.BtnOffLip, false));
            states.AddState(new[] { Android.Resource.Attribute.StatePressed }, Ui.Layered(normal, Pal.LipOf(normal), true));
            states.AddState(new int[0], Ui.Layered(normal, Pal.LipOf(normal), false));
            View.Background = states;
        }
    }

    public static class Ui
    {
        public static Context Ctx;
        static float density = 1;

        public static void Init(Context ctx)
        {
            Ctx = ctx;
            density = ctx.Resources.DisplayMetrics.Density;
        }

        public static int Dp(float v) { return (int)(v * density + 0.5f); }

        public static GradientDrawable Round(Color c, float radiusDp = 12)
        {
            var g = new GradientDrawable();
            g.SetColor(c.ToArgb());
            g.SetCornerRadius(Dp(radiusDp));
            return g;
        }

        public static GradientDrawable Circle(Color c)
        {
            var g = new GradientDrawable();
            g.SetShape(ShapeType.Oval);
            g.SetColor(c.ToArgb());
            return g;
        }

        // Лицевая часть поверх «губы»; в нажатом состоянии опущена вниз
        public static Drawable Layered(Color face, Color lip, bool pressed)
        {
            var ld = new LayerDrawable(new Drawable[] { Round(lip, 10), Round(face, 10) });
            int d = Dp(4);
            if (pressed) ld.SetLayerInset(1, 0, Dp(3), 0, Dp(1));
            else ld.SetLayerInset(1, 0, 0, 0, d);
            return ld;
        }

        static GradientDrawable Spacer(int dp)
        {
            var g = new GradientDrawable();
            g.SetColor(Color.Transparent.ToArgb());
            g.SetSize(Dp(dp), Dp(dp));
            return g;
        }

        // ---------- Раскладка ----------
        public static void Add(ViewGroup parent, View v, int w, int h, float weight = 0)
        {
            ViewGroup.LayoutParams lp;
            if (parent is LinearLayout) lp = new LinearLayout.LayoutParams(w, h, weight);
            else lp = new FrameLayout.LayoutParams(w, h);
            parent.AddView(v, lp);
        }

        static bool IsRow(ViewGroup p)
        {
            var l = p as LinearLayout;
            return l != null && l.Orientation == Orientation.Horizontal;
        }

        // В строке — растягивается по весу, в колонке — на всю ширину
        static void AddAuto(ViewGroup parent, View v, int heightPx, float weight = 1)
        {
            if (IsRow(parent)) Add(parent, v, 0, heightPx > 0 ? heightPx : ViewGroup.LayoutParams.MatchParent, weight);
            else Add(parent, v, ViewGroup.LayoutParams.MatchParent, heightPx > 0 ? heightPx : ViewGroup.LayoutParams.WrapContent);
        }

        public static LinearLayout Box(Orientation o, int spacingDp)
        {
            var l = new LinearLayout(Ctx) { Orientation = o };
            if (spacingDp > 0)
            {
                l.SetDividerDrawable(Spacer(spacingDp));
                l.ShowDividers = ShowDividers.Middle;
            }
            return l;
        }

        public static LinearLayout VList(ViewGroup parent, int spacingDp = 8)
        {
            var l = Box(Orientation.Vertical, spacingDp);
            AddAuto(parent, l, 0);
            return l;
        }

        public static LinearLayout Row(ViewGroup parent, int heightDp, int spacingDp = 8)
        {
            var l = Box(Orientation.Horizontal, spacingDp);
            l.SetGravity(GravityFlags.CenterVertical);
            AddAuto(parent, l, heightDp > 0 ? Dp(heightDp) : 0);
            return l;
        }

        // Вертикальный блок, занимающий свободное место в строке
        public static LinearLayout Stack(ViewGroup row)
        {
            var l = Box(Orientation.Vertical, 2);
            l.SetGravity(GravityFlags.CenterVertical);
            Add(row, l, 0, ViewGroup.LayoutParams.WrapContent, 1);
            return l;
        }

        // Утопленная ячейка — тёмная карточка внутри окна
        public static LinearLayout Card(ViewGroup parent, Color? color = null, int padDp = 14, int spacingDp = 8)
        {
            var l = Box(Orientation.Vertical, spacingDp);
            l.Background = Round(color ?? Pal.Card, 12);
            l.SetPadding(Dp(padDp), Dp(padDp), Dp(padDp), Dp(padDp));
            AddAuto(parent, l, 0);
            return l;
        }

        public static TextView Label(ViewGroup parent, string text, float sp, Color color, bool bold = false,
                                     GravityFlags gravity = GravityFlags.Start | GravityFlags.CenterVertical)
        {
            var t = new TextView(Ctx) { Text = text, Gravity = gravity };
            t.SetTextSize(ComplexUnitType.Sp, sp);
            t.SetTextColor(color);
            if (bold) t.Typeface = Typeface.DefaultBold;
            t.SetLineSpacing(0, 1.1f);
            if (parent != null)
            {
                if (IsRow(parent)) Add(parent, t, ViewGroup.LayoutParams.WrapContent, ViewGroup.LayoutParams.WrapContent);
                else Add(parent, t, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.WrapContent);
            }
            return t;
        }

        public static TextView Body(ViewGroup parent, float sp = 14)
        {
            return Label(parent, "", sp, Pal.Muted);
        }

        public static TextView Subtitle(ViewGroup parent, string text)
        {
            return Label(parent, text, 17, Pal.Text, true);
        }

        public static Btn Button(ViewGroup parent, string text, Color color, Action onClick, float sp = 15, int heightDp = 52)
        {
            var t = new TextView(Ctx) { Text = text, Gravity = GravityFlags.Center };
            t.SetTextColor(Pal.Text);
            t.Typeface = Typeface.DefaultBold;
            t.SetMaxLines(2);
            t.SetPadding(Dp(6), Dp(2), Dp(6), Dp(6));
            t.SetShadowLayer(1.5f, 0, Dp(1), Color.Argb(80, 0, 0, 0));
            t.SetAutoSizeTextTypeUniformWithConfiguration(9, (int)sp, 1, (int)ComplexUnitType.Sp);
            t.Clickable = true;
            t.Focusable = true;
            if (onClick != null) t.Click += (s, e) => onClick();
            AddAuto(parent, t, heightDp > 0 ? Dp(heightDp) : 0);
            return new Btn(t, color);
        }

        // Белый кружок с эмодзи — как номера в круглых значках референса
        public static TextView Badge(ViewGroup parent, string emoji, int sizeDp = 48)
        {
            var t = new TextView(Ctx) { Text = emoji, Gravity = GravityFlags.Center };
            t.SetTextSize(ComplexUnitType.Px, Dp(sizeDp) * 0.5f);
            t.Background = Circle(Color.White);
            t.SetIncludeFontPadding(false);
            Add(parent, t, Dp(sizeDp), Dp(sizeDp));
            return t;
        }

        // Шапка окна: полоса с заголовком капсом («STORE» / «INFO» в референсе)
        public static TextView Header(ViewGroup parent, string title)
        {
            var t = Label(null, title.ToUpperInvariant(), 18, Pal.Title, true, GravityFlags.Center);
            t.Background = Round(Pal.Header, 12);
            t.LetterSpacing = 0.05f;
            Add(parent, t, ViewGroup.LayoutParams.MatchParent, Dp(46));
            return t;
        }

        // Полоска прогресса (0..1000) в цветах интерфейса
        public static ProgressBar Progress(ViewGroup parent, Color color, int heightDp = 8)
        {
            var bg = Round(Pal.CardAlt, 4);
            var fg = new ClipDrawable(Round(color, 4), GravityFlags.Start, ClipDrawableOrientation.Horizontal);
            var ld = new LayerDrawable(new Drawable[] { bg, fg });
            ld.SetId(0, Android.Resource.Id.Background);
            ld.SetId(1, Android.Resource.Id.Progress);
            var bar = new ProgressBar(Ctx, null, Android.Resource.Attribute.ProgressBarStyleHorizontal)
            {
                Max = 1000,
                ProgressDrawable = ld,
            };
            Add(parent, bar, ViewGroup.LayoutParams.MatchParent, Dp(heightDp));
            return bar;
        }

        public static void Set(TextView t, string s)
        {
            if (t == null) return;
            var old = t.Tag == null ? null : t.Tag.ToString();
            if (old == s) return;
            t.Tag = s;
            t.Text = s;
        }

        public static void Set(TextView t, Rich r)
        {
            if (t == null) return;
            var k = r.Key;
            var old = t.Tag == null ? null : t.Tag.ToString();
            if (old == k) return;
            t.Tag = k;
            t.TextFormatted = r.Spannable;
        }

        public static void Show(View v, bool on)
        {
            if (v == null) return;
            var vis = on ? ViewStates.Visible : ViewStates.Gone;
            if (v.Visibility != vis) v.Visibility = vis;
        }

        public static Color Sign(double v) { return v >= 0 ? Pal.Green : Pal.Red; }
        public static string Signed(double v) { return (v >= 0 ? "+" : "") + Fmt.Money(v); }
    }
}
