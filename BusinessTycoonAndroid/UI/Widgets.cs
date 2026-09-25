using System;
using System.Collections.Generic;
using Android.Content;
using Android.Graphics;
using Android.Runtime;
using Android.Views;

namespace Tycoon.Droid
{
    // Линейный график цены акции
    public class LineChart : View
    {
        List<double> data;
        readonly Paint line = new Paint(PaintFlags.AntiAlias);
        readonly Path path = new Path();

        public LineChart(Context ctx) : base(ctx)
        {
            line.SetStyle(Paint.Style.Stroke);
            line.StrokeWidth = Ui.Dp(2.5f);
            line.StrokeJoin = Paint.Join.Round;
            line.StrokeCap = Paint.Cap.Round;
        }

        protected LineChart(IntPtr handle, JniHandleOwnership transfer) : base(handle, transfer) { }

        public void SetData(List<double> d, Color c)
        {
            data = d;
            line.Color = c;
            Invalidate();
        }

        protected override void OnDraw(Canvas canvas)
        {
            base.OnDraw(canvas);
            if (data == null || data.Count < 2) return;
            double min = double.MaxValue, max = double.MinValue;
            foreach (var v in data) { if (v < min) min = v; if (v > max) max = v; }
            if (max - min < 1e-9) { max += 1; min -= 1; }
            float w = Width, h = Height, pad = Ui.Dp(6);
            path.Reset();
            for (int i = 0; i < data.Count; i++)
            {
                float x = w * i / (data.Count - 1);
                float y = h - pad - (h - 2 * pad) * (float)((data[i] - min) / (max - min));
                if (i == 0) path.MoveTo(x, y); else path.LineTo(x, y);
            }
            canvas.DrawPath(path, line);
        }
    }
}
