using System.Collections.Generic;
using UnityEngine;
using UnityEngine.UI;

namespace Tycoon.UI
{
    // Линейный график цены акции
    public class LineChart : MaskableGraphic
    {
        List<double> data;
        public float Thickness = 6f;

        public void SetData(List<double> d, Color c)
        {
            data = d;
            color = c;
            SetVerticesDirty();
        }

        protected override void OnPopulateMesh(VertexHelper vh)
        {
            vh.Clear();
            if (data == null || data.Count < 2) return;
            var r = GetPixelAdjustedRect();
            double min = double.MaxValue, max = double.MinValue;
            foreach (var v in data) { if (v < min) min = v; if (v > max) max = v; }
            if (max - min < 1e-9) { max += 1; min -= 1; }
            float pad = Thickness;
            int n = data.Count;
            Vector2 prev = Point(r, 0, n, data[0], min, max, pad);
            for (int i = 1; i < n; i++)
            {
                var p = Point(r, i, n, data[i], min, max, pad);
                Segment(vh, prev, p);
                prev = p;
            }
        }

        static Vector2 Point(Rect r, int i, int n, double v, double min, double max, float pad)
        {
            float x = r.xMin + r.width * i / (n - 1);
            float y = r.yMin + pad + (r.height - 2 * pad) * (float)((v - min) / (max - min));
            return new Vector2(x, y);
        }

        void Segment(VertexHelper vh, Vector2 a, Vector2 b)
        {
            var dir = (b - a).normalized;
            var nrm = new Vector2(-dir.y, dir.x) * (Thickness * 0.5f);
            int idx = vh.currentVertCount;
            var v = UIVertex.simpleVert;
            v.color = color;
            v.position = a - nrm; vh.AddVert(v);
            v.position = a + nrm; vh.AddVert(v);
            v.position = b + nrm; vh.AddVert(v);
            v.position = b - nrm; vh.AddVert(v);
            vh.AddTriangle(idx, idx + 1, idx + 2);
            vh.AddTriangle(idx + 2, idx + 3, idx);
        }
    }

    // Всплывающая надпись «+$X» при нажатии
    public class FloatText : MonoBehaviour
    {
        const float Life = 0.9f;
        float age;
        Text text;
        RectTransform rt;

        public static void Spawn(Transform parent, Vector2 pos, string s, Color c)
        {
            var t = UIKit.Label(parent, s, 48, c, TextAnchor.MiddleCenter, FontStyle.Bold);
            t.horizontalOverflow = HorizontalWrapMode.Overflow;
            t.rectTransform.sizeDelta = new Vector2(400, 80);
            t.rectTransform.anchoredPosition = pos;
            var sh = t.gameObject.AddComponent<Outline>();
            sh.effectColor = new Color(0, 0, 0, 0.5f);
            var le = t.gameObject.AddComponent<LayoutElement>();
            le.ignoreLayout = true;
            var f = t.gameObject.AddComponent<FloatText>();
            f.text = t;
            f.rt = t.rectTransform;
        }

        void Update()
        {
            age += Time.unscaledDeltaTime;
            rt.anchoredPosition += new Vector2(0, 260 * Time.unscaledDeltaTime);
            var c = text.color;
            c.a = Mathf.Clamp01(1 - age / Life);
            text.color = c;
            if (age >= Life) Destroy(gameObject);
        }
    }

    // Небольшая «пружинка» при нажатии на главную кнопку
    public class Pulse : MonoBehaviour
    {
        float t = 1;

        public void Kick() { t = 0; }

        void Update()
        {
            if (t >= 1) return;
            t = Mathf.Min(1, t + Time.unscaledDeltaTime * 6);
            float s = 1 - 0.06f * Mathf.Sin(t * Mathf.PI);
            transform.localScale = new Vector3(s, s, 1);
        }
    }
}
