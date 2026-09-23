using System;
using UnityEngine;
using UnityEngine.UI;

namespace Tycoon.UI
{
    // Палитра в стиле «индиго-окна + золотые кнопки» (по референсу мобильных окон)
    public static class Pal
    {
        public static readonly Color Bg = Hex("#14152A");        // фон за окнами
        public static readonly Color Panel = Hex("#3A3E6C");     // тело окна
        public static readonly Color Header = Hex("#30335C");    // шапка окна / панели
        public static readonly Color Card = Hex("#2D3158");      // утопленная ячейка
        public static readonly Color CardAlt = Hex("#262A4C");   // поле ввода, график
        public static readonly Color Line = Hex("#4A4F82");      // разделители
        public static readonly Color Text = Hex("#FFFFFF");
        public static readonly Color Muted = Hex("#AEB2D8");
        public static readonly Color Title = Hex("#C9CCEB");     // заголовки окон
        public static readonly Color Gold = Hex("#F2C94C");
        public static readonly Color GoldLip = Hex("#B98F2A");
        public static readonly Color Blue = Hex("#4B8FE0");
        public static readonly Color BlueLip = Hex("#2F66AA");
        public static readonly Color Green = Hex("#3DCB7A");
        public static readonly Color GreenLip = Hex("#279A57");
        public static readonly Color Red = Hex("#E8605A");
        public static readonly Color RedLip = Hex("#A93F3B");
        public static readonly Color Btn = Hex("#4E5390");       // нейтральная кнопка
        public static readonly Color BtnLip = Hex("#383C6C");
        public static readonly Color BtnOff = Hex("#454970");
        public static readonly Color BtnOffLip = Hex("#35385A");
        public static readonly Color TextDark = Hex("#2A2D52");

        // Нижняя «губа» 3D-кнопки — более тёмный оттенок основного цвета
        public static Color LipOf(Color c)
        {
            if (c == Gold) return GoldLip;
            if (c == Blue) return BlueLip;
            if (c == Green) return GreenLip;
            if (c == Red) return RedLip;
            if (c == Btn) return BtnLip;
            return Color.Lerp(c, Color.black, 0.3f);
        }

        public static Color Hex(string hex)
        {
            Color c;
            return ColorUtility.TryParseHtmlString(hex, out c) ? c : Color.magenta;
        }
    }

    // Набор фабричных методов: весь интерфейс строится из кода, без префабов и сцен
    public static class UIKit
    {
        static Font font;
        static Sprite rounded;
        static Sprite circle;

        public static Font Font
        {
            get
            {
                if (font != null) return font;
                // Unity 2022.2+ — LegacyRuntime.ttf, более старые версии — Arial.ttf
                try { font = Resources.GetBuiltinResource<Font>("LegacyRuntime.ttf"); } catch (Exception) { font = null; }
                if (font == null)
                {
                    try { font = Resources.GetBuiltinResource<Font>("Arial.ttf"); } catch (Exception) { font = null; }
                }
                if (font == null) font = Font.CreateDynamicFontFromOSFont("Roboto", 32);
                return font;
            }
        }

        // Скруглённый прямоугольник для 9-slice, генерируется один раз
        public static Sprite Rounded
        {
            get
            {
                if (rounded != null) return rounded;
                const int size = 64, rad = 22;
                var tex = new Texture2D(size, size, TextureFormat.RGBA32, false);
                tex.wrapMode = TextureWrapMode.Clamp;
                var px = new Color32[size * size];
                for (int y = 0; y < size; y++)
                {
                    for (int x = 0; x < size; x++)
                    {
                        float cx = Mathf.Clamp(x + 0.5f, rad, size - rad);
                        float cy = Mathf.Clamp(y + 0.5f, rad, size - rad);
                        float d = Vector2.Distance(new Vector2(x + 0.5f, y + 0.5f), new Vector2(cx, cy));
                        byte a = (byte)(Mathf.Clamp01(rad - d + 0.5f) * 255);
                        px[y * size + x] = new Color32(255, 255, 255, a);
                    }
                }
                tex.SetPixels32(px);
                tex.Apply();
                rounded = Sprite.Create(tex, new Rect(0, 0, size, size), new Vector2(0.5f, 0.5f), 100, 0,
                    SpriteMeshType.FullRect, new Vector4(rad, rad, rad, rad));
                return rounded;
            }
        }

        public static Sprite Circle
        {
            get
            {
                if (circle != null) return circle;
                const int size = 128;
                var tex = new Texture2D(size, size, TextureFormat.RGBA32, false);
                var px = new Color32[size * size];
                float r = size / 2f;
                for (int y = 0; y < size; y++)
                    for (int x = 0; x < size; x++)
                    {
                        float d = Vector2.Distance(new Vector2(x + 0.5f, y + 0.5f), new Vector2(r, r));
                        px[y * size + x] = new Color32(255, 255, 255, (byte)(Mathf.Clamp01(r - d) * 255));
                    }
                tex.SetPixels32(px);
                tex.Apply();
                circle = Sprite.Create(tex, new Rect(0, 0, size, size), new Vector2(0.5f, 0.5f), 100);
                return circle;
            }
        }

        public static RectTransform Rect(Transform parent, string name)
        {
            var go = new GameObject(name, typeof(RectTransform));
            go.layer = 5; // UI
            var rt = (RectTransform)go.transform;
            rt.SetParent(parent, false);
            return rt;
        }

        public static void Stretch(RectTransform rt, float l = 0, float b = 0, float r = 0, float t = 0)
        {
            rt.anchorMin = Vector2.zero;
            rt.anchorMax = Vector2.one;
            rt.offsetMin = new Vector2(l, b);
            rt.offsetMax = new Vector2(-r, -t);
        }

        public static Image Img(Transform parent, string name, Color c, bool round = true)
        {
            var rt = Rect(parent, name);
            var img = rt.gameObject.AddComponent<Image>();
            img.color = c;
            if (round)
            {
                img.sprite = Rounded;
                img.type = Image.Type.Sliced;
                img.pixelsPerUnitMultiplier = 1.4f;
            }
            return img;
        }

        public static Text Label(Transform parent, string text, int size, Color color,
                                 TextAnchor anchor = TextAnchor.MiddleLeft, FontStyle style = FontStyle.Normal)
        {
            var rt = Rect(parent, "Text");
            var t = rt.gameObject.AddComponent<Text>();
            t.font = Font;
            t.text = text;
            t.fontSize = size;
            t.color = color;
            t.alignment = anchor;
            t.fontStyle = style;
            t.supportRichText = true;
            t.horizontalOverflow = HorizontalWrapMode.Wrap;
            t.verticalOverflow = VerticalWrapMode.Overflow;
            t.raycastTarget = false;
            return t;
        }

        public static LayoutElement LE(Component c, float prefH = -1, float flexW = -1, float prefW = -1, float minH = -1)
        {
            // без ?? — у объектов Unity переопределён оператор сравнения с null
            var le = c.GetComponent<LayoutElement>();
            if (le == null) le = c.gameObject.AddComponent<LayoutElement>();
            if (prefH >= 0) le.preferredHeight = prefH;
            if (minH >= 0) le.minHeight = minH;
            if (flexW >= 0) le.flexibleWidth = flexW;
            if (prefW >= 0) le.preferredWidth = prefW;
            return le;
        }

        public static VerticalLayoutGroup VList(Component c, float spacing = 12, int pad = 0)
        {
            var v = c.gameObject.AddComponent<VerticalLayoutGroup>();
            v.spacing = spacing;
            v.padding = new RectOffset(pad, pad, pad, pad);
            v.childControlWidth = true;
            v.childControlHeight = true;
            v.childForceExpandWidth = true;
            v.childForceExpandHeight = false;
            return v;
        }

        public static RectTransform Row(Transform parent, float height, float spacing = 12)
        {
            var rt = Rect(parent, "Row");
            var h = rt.gameObject.AddComponent<HorizontalLayoutGroup>();
            h.spacing = spacing;
            h.childControlWidth = true;
            h.childControlHeight = true;
            h.childForceExpandWidth = false;
            h.childForceExpandHeight = true;
            h.childAlignment = TextAnchor.MiddleLeft;
            if (height > 0) LE(rt, height);
            return rt;
        }

        // Карточка с вертикальным списком внутри
        public static RectTransform Card(Transform parent, Color? color = null, int pad = 28, float spacing = 14)
        {
            var img = Img(parent, "Card", color ?? Pal.Card);
            VList(img, spacing, pad);
            return img.rectTransform;
        }

        // Объёмная кнопка: тёмная «губа» снизу + лицевая часть, как в референсе
        public class Btn
        {
            public Button Button;
            public Image Lip;
            public Image Face;
            public Text Label;
            public Color Normal;

            public bool Interactable
            {
                set
                {
                    if (Button.interactable == value) return;
                    Button.interactable = value;
                    Face.color = value ? Normal : Pal.BtnOff;
                    Lip.color = value ? Pal.LipOf(Normal) : Pal.BtnOffLip;
                    Label.color = value ? Pal.Text : Pal.Muted;
                }
            }

            public string Text
            {
                set { if (Label.text != value) Label.text = value; }
            }

            public void SetColor(Color c)
            {
                if (Normal == c) return;
                Normal = c;
                if (!Button.interactable) return;
                Face.color = c;
                Lip.color = Pal.LipOf(c);
            }
        }

        public static Btn Button(Transform parent, string text, Color bg, Action onClick, int fontSize = 34, float height = 110)
        {
            var lip = Img(parent, "Button", Pal.LipOf(bg));
            lip.raycastTarget = true;
            var face = Img(lip.transform, "Face", bg);
            face.raycastTarget = false;
            Stretch(face.rectTransform, 0, 8, 0, 0);
            var b = lip.gameObject.AddComponent<Button>();
            var colors = b.colors;
            colors.normalColor = Color.white;
            colors.highlightedColor = Color.white;
            colors.pressedColor = new Color(0.8f, 0.8f, 0.8f);
            colors.selectedColor = Color.white;
            colors.disabledColor = Color.white;
            b.colors = colors;
            b.targetGraphic = face;
            var t = Label(face.transform, text, fontSize, Pal.Text, TextAnchor.MiddleCenter, FontStyle.Bold);
            Stretch(t.rectTransform, 12, 4, 12, 4);
            t.resizeTextForBestFit = true;
            t.resizeTextMinSize = 18;
            t.resizeTextMaxSize = fontSize;
            t.verticalOverflow = VerticalWrapMode.Truncate;
            var sh = t.gameObject.AddComponent<Shadow>();
            sh.effectColor = new Color(0, 0, 0, 0.3f);
            sh.effectDistance = new Vector2(0, -2);
            if (onClick != null) b.onClick.AddListener(() => onClick());
            // растягиваем по ширине в строках; высота 0 — задаёт родительская раскладка
            LE(lip, height > 0 ? height : -1, 1);
            return new Btn { Button = b, Lip = lip, Face = face, Label = t, Normal = bg };
        }

        // Шапка окна: тёмная полоса с заголовком капсом, как «STORE» / «INFO» в референсе
        public static Text Header(Transform parent, string title)
        {
            var bar = Img(parent, "Header", Pal.Header);
            LE(bar, 96);
            var t = Label(bar.transform, title.ToUpperInvariant(), 40, Pal.Title, TextAnchor.MiddleCenter, FontStyle.Bold);
            Stretch(t.rectTransform);
            return t;
        }

        // Белый кружок с буквами (как номера в референсе); цвет букв — цвет объекта.
        // Эмодзи в Unity Text не отображаются, поэтому иконки текстовые.
        public static Image Badge(Transform parent, string abbr, string hex, float size = 96)
        {
            var img = Rect(parent, "Badge").gameObject.AddComponent<Image>();
            img.sprite = Circle;
            img.color = Color.white;
            img.preserveAspect = true;
            LE(img, size, 0, size);
            var t = Label(img.transform, abbr, (int)(size * 0.38f), Color.Lerp(Pal.Hex(hex), Color.black, 0.2f), TextAnchor.MiddleCenter, FontStyle.Bold);
            Stretch(t.rectTransform);
            return img;
        }

        // Вертикальный скролл с автоматической высотой контента
        public static RectTransform Scroll(Transform parent, out ScrollRect scroll)
        {
            var root = Rect(parent, "Scroll");
            Stretch(root);
            scroll = root.gameObject.AddComponent<ScrollRect>();
            scroll.horizontal = false;
            scroll.movementType = ScrollRect.MovementType.Elastic;
            scroll.scrollSensitivity = 40;

            var viewport = Rect(root, "Viewport");
            Stretch(viewport);
            viewport.gameObject.AddComponent<RectMask2D>();
            var vpImg = viewport.gameObject.AddComponent<Image>();
            vpImg.color = new Color(0, 0, 0, 0); // ловит свайпы

            var content = Rect(viewport, "Content");
            content.anchorMin = new Vector2(0, 1);
            content.anchorMax = new Vector2(1, 1);
            content.pivot = new Vector2(0.5f, 1);
            content.offsetMin = Vector2.zero;
            content.offsetMax = Vector2.zero;
            var v = VList(content, 20, 0);
            v.padding = new RectOffset(24, 24, 24, 48);
            var fit = content.gameObject.AddComponent<ContentSizeFitter>();
            fit.verticalFit = ContentSizeFitter.FitMode.PreferredSize;

            scroll.viewport = viewport;
            scroll.content = content;
            return content;
        }

        public static InputField Input(Transform parent, string placeholder, int fontSize = 36)
        {
            var img = Img(parent, "Input", Pal.CardAlt);
            img.raycastTarget = true;
            LE(img, 110, 1);
            var field = img.gameObject.AddComponent<InputField>();
            var text = Label(img.transform, "", fontSize, Pal.Text);
            text.supportRichText = false;
            Stretch(text.rectTransform, 24, 8, 24, 8);
            var ph = Label(img.transform, placeholder, fontSize, Pal.Muted);
            ph.fontStyle = FontStyle.Italic;
            Stretch(ph.rectTransform, 24, 8, 24, 8);
            field.textComponent = text;
            field.placeholder = ph;
            field.targetGraphic = img;
            field.lineType = InputField.LineType.SingleLine;
            field.characterLimit = 24;
            return field;
        }

        public static void Clear(Transform t)
        {
            for (int i = t.childCount - 1; i >= 0; i--)
            {
                var c = t.GetChild(i).gameObject;
                c.SetActive(false);
                UnityEngine.Object.Destroy(c);
            }
        }

        public static void SetText(Text t, string s) { if (t != null && t.text != s) t.text = s; }

        public static void SetActive(Component c, bool on) { if (c != null && c.gameObject.activeSelf != on) c.gameObject.SetActive(on); }

        public static string Col(string s, Color c) { return "<color=#" + ColorUtility.ToHtmlStringRGB(c) + ">" + s + "</color>"; }
    }
}
