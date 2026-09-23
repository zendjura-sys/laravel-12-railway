using System.Text;
using UnityEngine;
using UnityEngine.UI;

namespace Tycoon.UI
{
    // Биржа: акции (график, покупка/продажа), опционы Call/Put, портфель
    public class MarketScreen : GameScreen
    {
        public MarketScreen(UIRoot ui) : base(ui) { }
        public override string Title { get { return "Биржа"; } }

        static readonly string[] SubNames = { "Акции", "Опционы", "Портфель" };
        static readonly long[] Qtys = { 1, 10, 100, 0 };
        static readonly string[] QtyNames = { "1", "10", "100", "MAX" };

        int sub;
        string selected = "KOLS";
        long qty = 10;
        // параметры опциона
        bool optCall = true;
        int optStrike = 2, optExp = 1, optQty = 1;

        UIKit.Btn[] subBtns;

        // Акции
        Text selTitle, selPrice, selOwn;
        LineChart chart;
        UIKit.Btn[] qtyBtns;
        UIKit.Btn buyBtn, sellBtn;
        Text[] rowPrice;
        UIKit.Btn[] rowBtn;
        string[] rowTicker;

        // Опционы
        UIKit.Btn optTicker, optCallBtn, optPutBtn, optStrikeBtn, optBuy;
        UIKit.Btn[] optExpBtns;
        Text optQtyText, optQuote;
        Text[] posText;
        UIKit.Btn[] posClose;
        int[] posIds;

        // Портфель
        Text portText;

        protected override string Signature()
        {
            var sb = new StringBuilder();
            sb.Append(sub).Append('|');
            foreach (var st in S.stocks) sb.Append(st.t).Append(',');
            if (sub == 1) foreach (var o in S.options) sb.Append(o.id).Append(';');
            return sb.ToString();
        }

        protected override void Build()
        {
            var tabs = UIKit.Row(Content, 100, 12);
            subBtns = new UIKit.Btn[SubNames.Length];
            for (int i = 0; i < SubNames.Length; i++)
            {
                int k = i;
                subBtns[i] = UIKit.Button(tabs, SubNames[i], Pal.Btn, () => { sub = k; ForceRebuild(); Refresh(); ScrollTop(); }, 32, 0);
            }
            if (E.Stock(selected) == null) selected = S.stocks[0].t;
            if (sub == 0) BuildStocks();
            else if (sub == 1) BuildOptions();
            else BuildPortfolio();
        }

        // ---------------- Акции ----------------
        void BuildStocks()
        {
            var card = Cell();
            selTitle = UIKit.Label(card, "", 36, Pal.Text, TextAnchor.MiddleLeft, FontStyle.Bold);
            selPrice = UIKit.Label(card, "", 44, Pal.Gold, TextAnchor.MiddleLeft, FontStyle.Bold);

            var chartBg = UIKit.Img(card, "Chart", Pal.CardAlt);
            UIKit.LE(chartBg, 320);
            var crt = UIKit.Rect(chartBg.transform, "Line");
            UIKit.Stretch(crt, 16, 16, 16, 16);
            chart = crt.gameObject.AddComponent<LineChart>();
            chart.raycastTarget = false;

            selOwn = Body(card, "", 30);

            var qrow = UIKit.Row(card, 90, 12);
            qtyBtns = new UIKit.Btn[Qtys.Length];
            for (int i = 0; i < Qtys.Length; i++)
            {
                long q = Qtys[i];
                qtyBtns[i] = UIKit.Button(qrow, QtyNames[i], Pal.Btn, () => { qty = q; Sync(); }, 30, 0);
            }
            var brow = UIKit.Row(card, 120, 16);
            buyBtn = UIKit.Button(brow, "", Pal.Green, () => UI.Try(E.BuyStock(selected, BuyQty()), "Недостаточно средств"), 32, 0);
            sellBtn = UIKit.Button(brow, "", Pal.Red, () => UI.Try(E.SellStock(selected, SellQty()), "Нет акций для продажи"), 32, 0);

            int n = S.stocks.Count;
            rowPrice = new Text[n];
            rowBtn = new UIKit.Btn[n];
            rowTicker = new string[n];
            for (int i = 0; i < n; i++)
            {
                var st = S.stocks[i];
                var info = E.Info(st.t);
                if (info == null) continue;
                rowTicker[i] = st.t;
                var t = st.t;
                // строка списка — как «Object Title / Description + цена» в референсе
                var btn = UIKit.Button(Content, "", Pal.Card, () => { selected = t; Sync(); ScrollTop(); }, 30, 124);
                btn.Label.text = "";
                var row = UIKit.Rect(btn.Face.transform, "Row");
                UIKit.Stretch(row, 20, 6, 20, 6);
                var h = row.gameObject.AddComponent<HorizontalLayoutGroup>();
                h.spacing = 18;
                h.childControlWidth = h.childControlHeight = true;
                h.childForceExpandWidth = false;
                h.childForceExpandHeight = true;
                h.childAlignment = TextAnchor.MiddleLeft;
                UIKit.Badge(row, st.t.Substring(0, 2), info.Color, 84);
                var stack = VStack(row, 0);
                UIKit.Label(stack, st.t + (info.Own != null ? "  (ваша)" : ""), 32, Pal.Text, TextAnchor.MiddleLeft, FontStyle.Bold);
                UIKit.Label(stack, info.Name, 24, Pal.Muted, TextAnchor.MiddleLeft);
                rowPrice[i] = UIKit.Label(row, "", 30, Pal.Text, TextAnchor.MiddleRight, FontStyle.Bold);
                UIKit.LE(rowPrice[i], -1, 0, 260);
                rowBtn[i] = btn;
            }
        }

        long BuyQty() { return qty > 0 ? qty : E.MaxBuyShares(selected); }
        long SellQty() { var st = E.Stock(selected); return qty > 0 ? qty : st.own; }

        static string Change(StockState st)
        {
            double base0 = st.h.Count > 0 ? st.h[0] : st.p;
            double ch = st.p / base0 - 1;
            return UIKit.Col(Fmt.Pct(ch), ch >= 0 ? Pal.Green : Pal.Red);
        }

        void SyncStocks()
        {
            var st = E.Stock(selected);
            var info = E.Info(selected);
            UIKit.SetText(selTitle, info.Name + " · " + st.t);
            UIKit.SetText(selPrice, Fmt.Money(st.p) + "  <size=30>" + Change(st) + " за 5 мин</size>");
            chart.SetData(st.h, st.h.Count > 1 && st.p >= st.h[0] ? Pal.Green : Pal.Red);

            var sb = new StringBuilder();
            sb.Append("У вас: ").Append(st.own).Append(" шт");
            if (st.own > 0)
            {
                double pl = (st.p - st.avg) * st.own;
                sb.Append(" · ср. цена ").Append(Fmt.Money(st.avg)).Append(" · ")
                  .Append(UIKit.Col((pl >= 0 ? "+" : "") + Fmt.Money(pl), pl >= 0 ? Pal.Green : Pal.Red));
            }
            if (info.Div > 0) sb.Append("\nДивиденды: ").Append(Fmt.Pct(info.Div, false)).Append(" от цены каждую минуту");
            if (info.Own != null) sb.Append("\nВаша компания: доля ").Append(Fmt.Pct(E.CoOwnFrac(info.Own), false)).Append(" определяет ваш доход от неё");
            UIKit.SetText(selOwn, sb.ToString());

            for (int i = 0; i < Qtys.Length; i++) qtyBtns[i].SetColor(Qtys[i] == qty ? Pal.Green : Pal.Btn);
            long bq = BuyQty(), sq = SellQty();
            buyBtn.Text = "Купить " + bq + "\n" + Fmt.Money(bq * st.p * (1 + GameData.TradeFee));
            buyBtn.Interactable = bq > 0 && E.MaxBuyShares(selected) >= bq;
            sellBtn.Text = "Продать " + System.Math.Min(sq, st.own) + "\n" + Fmt.Money(System.Math.Min(sq, st.own) * st.p);
            sellBtn.Interactable = st.own > 0;

            for (int i = 0; i < rowTicker.Length; i++)
            {
                if (rowTicker[i] == null) continue;
                var s = E.Stock(rowTicker[i]);
                UIKit.SetText(rowPrice[i], Fmt.Money(s.p) + "\n<size=24>" + Change(s) + "</size>");
                rowBtn[i].SetColor(rowTicker[i] == selected ? Pal.Line : Pal.Card);
            }
        }

        // ---------------- Опционы ----------------
        void BuildOptions()
        {
            var card = Cell();
            Subtitle(card, "Купить опцион");
            var help = Body(card, "Call зарабатывает, если цена к экспирации выше страйка, Put — если ниже. " +
                                  "1 контракт = " + GameData.OptSize + " акций. Убыток ограничен премией.", 26);
            UIKit.LE(help, -1);

            optTicker = UIKit.Button(card, "", Pal.Btn, NextTicker, 32, 100);

            var trow = UIKit.Row(card, 100, 12);
            optCallBtn = UIKit.Button(trow, "CALL (рост)", Pal.Btn, () => { optCall = true; Sync(); }, 30, 0);
            optPutBtn = UIKit.Button(trow, "PUT (падение)", Pal.Btn, () => { optCall = false; Sync(); }, 30, 0);

            optStrikeBtn = UIKit.Button(card, "", Pal.Btn, () => { optStrike = (optStrike + 1) % GameData.OptStrikes.Length; Sync(); }, 30, 100);

            var erow = UIKit.Row(card, 100, 12);
            optExpBtns = new UIKit.Btn[GameData.OptExpiries.Length];
            for (int i = 0; i < optExpBtns.Length; i++)
            {
                int k = i;
                optExpBtns[i] = UIKit.Button(erow, Fmt.Time(GameData.OptExpiries[i]), Pal.Btn, () => { optExp = k; Sync(); }, 30, 0);
            }

            var qrow = UIKit.Row(card, 100, 12);
            UIKit.Button(qrow, "-10", Pal.Btn, () => { optQty = Mathf.Max(1, optQty - 10); Sync(); }, 30, 0);
            UIKit.Button(qrow, "-1", Pal.Btn, () => { optQty = Mathf.Max(1, optQty - 1); Sync(); }, 30, 0);
            optQtyText = UIKit.Label(qrow, "", 34, Pal.Text, TextAnchor.MiddleCenter, FontStyle.Bold);
            UIKit.LE(optQtyText, -1, 1.4f);
            UIKit.Button(qrow, "+1", Pal.Btn, () => { optQty++; Sync(); }, 30, 0);
            UIKit.Button(qrow, "+10", Pal.Btn, () => { optQty += 10; Sync(); }, 30, 0);

            optQuote = Body(card, "", 30);
            optBuy = UIKit.Button(card, "", Pal.Gold, BuyOption, 34, 120);

            var pc = Cell();
            Subtitle(pc, "Открытые позиции");
            int n = S.options.Count;
            posText = new Text[n];
            posClose = new UIKit.Btn[n];
            posIds = new int[n];
            if (n == 0) Body(pc, "Нет открытых опционов", 30);
            for (int i = 0; i < n; i++)
            {
                var o = S.options[i];
                posIds[i] = o.id;
                var cell = UIKit.Card(pc, Pal.CardAlt, 20, 10);
                posText[i] = Body(cell, "", 28);
                int id = o.id;
                posClose[i] = UIKit.Button(cell, "", Pal.Blue, () => UI.Try(E.CloseOption(id), "Позиция уже закрыта"), 28, 96);
            }
        }

        void NextTicker()
        {
            int i = S.stocks.FindIndex(x => x.t == selected);
            selected = S.stocks[(i + 1) % S.stocks.Count].t;
            Sync();
        }

        void BuyOption()
        {
            bool ok = E.BuyOption(selected, optCall, GameData.OptStrikes[optStrike], GameData.OptExpiries[optExp], optQty);
            if (ok) UI.Toast("Опцион куплен");
            UI.Try(ok, "Недостаточно средств");
        }

        void SyncOptions()
        {
            var st = E.Stock(selected);
            optTicker.Text = "Акция: " + st.t + " · " + Fmt.Money(st.p) + "   (сменить)";
            optCallBtn.SetColor(optCall ? Pal.Green : Pal.Btn);
            optPutBtn.SetColor(!optCall ? Pal.Red : Pal.Btn);
            double k = GameData.OptStrikes[optStrike];
            var q = E.Quote(selected, optCall, k, GameData.OptExpiries[optExp]);
            optStrikeBtn.Text = "Страйк: " + Fmt.Money(q.Strike) + " (" + Fmt.Pct(k - 1) + ")   (сменить)";
            for (int i = 0; i < optExpBtns.Length; i++) optExpBtns[i].SetColor(i == optExp ? Pal.Green : Pal.Btn);
            optQtyText.text = optQty + " контр.";

            double total = q.Premium * optQty;
            double be = optCall ? q.Strike + q.Premium / GameData.OptSize : q.Strike - q.Premium / GameData.OptSize;
            UIKit.SetText(optQuote,
                "Премия за контракт: " + Fmt.Money(q.Premium) + "\n" +
                "Точка безубыточности: " + Fmt.Money(be) + "\n" +
                "Экспирация через " + Fmt.Time(GameData.OptExpiries[optExp]));
            optBuy.Text = "Купить за " + Fmt.Money(total);
            optBuy.Interactable = S.money >= total;

            for (int i = 0; i < posIds.Length; i++)
            {
                var o = S.options.Find(x => x.id == posIds[i]);
                if (o == null) continue;
                double v = E.OptionValue(o);
                double pl = v * (1 - GameData.OptSpread) - o.paid;
                UIKit.SetText(posText[i],
                    UIKit.Col((o.call ? "CALL " : "PUT ") + o.t, o.call ? Pal.Green : Pal.Red) + " · страйк " + Fmt.Money(o.strike) +
                    " · " + o.qty + " контр.\n" +
                    "Цена акции: " + Fmt.Money(E.Stock(o.t).p) + " · осталось " + Fmt.Time(o.expiry - S.t) + "\n" +
                    "Стоимость: " + Fmt.Money(v) + " · " + UIKit.Col((pl >= 0 ? "+" : "") + Fmt.Money(pl), pl >= 0 ? Pal.Green : Pal.Red));
                posClose[i].Text = "Закрыть досрочно за " + Fmt.Money(v * (1 - GameData.OptSpread));
            }
        }

        // ---------------- Портфель ----------------
        void BuildPortfolio()
        {
            var card = Cell();
            Subtitle(card, "Ваш портфель");
            portText = Body(card, "", 30);
            portText.lineSpacing = 1.15f;
        }

        void SyncPortfolio()
        {
            var sb = new StringBuilder();
            double total = 0, pl = 0;
            foreach (var st in S.stocks)
            {
                if (st.own == 0) continue;
                double v = st.own * st.p, p = (st.p - st.avg) * st.own;
                total += v; pl += p;
                sb.Append(UIKit.Col(st.t, Pal.Text)).Append(": ").Append(st.own).Append(" шт · ").Append(Fmt.Money(v)).Append(" · ")
                  .Append(UIKit.Col((p >= 0 ? "+" : "") + Fmt.Money(p), p >= 0 ? Pal.Green : Pal.Red)).Append('\n');
            }
            double ov = 0;
            foreach (var o in S.options) ov += E.OptionValue(o);
            var head = "Акции: " + UIKit.Col(Fmt.Money(total), Pal.Gold) + "\n" +
                       "Прибыль/убыток: " + UIKit.Col((pl >= 0 ? "+" : "") + Fmt.Money(pl), pl >= 0 ? Pal.Green : Pal.Red) + "\n" +
                       "Опционы: " + Fmt.Money(ov) + " (" + S.options.Count + " поз.)\n\n";
            UIKit.SetText(portText, head + (sb.Length == 0 ? "Пока нет акций. Купите их во вкладке «Акции»." : sb.ToString().TrimEnd()));
        }

        protected override void Sync()
        {
            for (int i = 0; i < subBtns.Length; i++) subBtns[i].SetColor(i == sub ? Pal.Green : Pal.Btn);
            if (sub == 0) SyncStocks();
            else if (sub == 1) SyncOptions();
            else SyncPortfolio();
        }
    }
}
