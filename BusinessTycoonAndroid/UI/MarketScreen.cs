using System.Text;
using Android.Views;
using Android.Widget;

namespace Tycoon.Droid
{
    // Биржа: акции (график, покупка/продажа), опционы Call/Put, портфель
    public class MarketScreen : GameScreen
    {
        public MarketScreen(Shell ui) : base(ui) { }
        public override string Title { get { return "Биржа"; } }

        static readonly string[] SubNames = { "Акции", "Опционы", "Портфель" };
        static readonly long[] Qtys = { 1, 10, 100, 0 };
        static readonly string[] QtyNames = { "1", "10", "100", "MAX" };

        int sub;
        string selected = "KOLS";
        long qty = 10;
        bool optCall = true;
        int optStrike = 2, optExp = 1, optQty = 1;

        Btn[] subBtns;

        // Акции
        TextView selTitle, selPrice, selOwn;
        LineChart chart;
        Btn[] qtyBtns;
        Btn buyBtn, sellBtn;
        TextView[] rowPrice;
        LinearLayout[] rowViews;
        string[] rowTicker;
        string paintedSel;

        // Опционы
        Btn optTicker, optCallBtn, optPutBtn, optStrikeBtn, optBuy;
        Btn[] optExpBtns;
        TextView optQtyText, optQuote;
        TextView[] posText;
        Btn[] posClose;
        int[] posIds;

        // Портфель
        TextView portText;

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
            var tabs = Ui.Row(Content, 46, 8);
            subBtns = new Btn[SubNames.Length];
            for (int i = 0; i < SubNames.Length; i++)
            {
                int k = i;
                subBtns[i] = Ui.Button(tabs, SubNames[i], Pal.Btn, () => { sub = k; ForceRebuild(); Refresh(); ScrollTop(); }, 15, 0);
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
            selTitle = Ui.Label(card, "", 17, Pal.Text, true);
            selPrice = Ui.Label(card, "", 22, Pal.Gold, true);

            chart = new LineChart(Ui.Ctx) { Background = Ui.Round(Pal.CardAlt, 10) };
            int p = Ui.Dp(8);
            chart.SetPadding(p, p, p, p);
            Ui.Add(card, chart, ViewGroup.LayoutParams.MatchParent, Ui.Dp(150));

            selOwn = Ui.Body(card, 14);

            var qrow = Ui.Row(card, 44, 8);
            qtyBtns = new Btn[Qtys.Length];
            for (int i = 0; i < Qtys.Length; i++)
            {
                long q = Qtys[i];
                qtyBtns[i] = Ui.Button(qrow, QtyNames[i], Pal.Btn, () => { qty = q; Sync(); }, 14, 0);
            }
            var brow = Ui.Row(card, 56, 10);
            buyBtn = Ui.Button(brow, "", Pal.Green, () => UI.Try(E.BuyStock(selected, BuyQty()), "Недостаточно средств"), 15, 0);
            sellBtn = Ui.Button(brow, "", Pal.Red, () => UI.Try(E.SellStock(selected, SellQty()), "Нет акций для продажи"), 15, 0);

            int n = S.stocks.Count;
            paintedSel = null;
            rowPrice = new TextView[n];
            rowViews = new LinearLayout[n];
            rowTicker = new string[n];
            for (int i = 0; i < n; i++)
            {
                var st = S.stocks[i];
                var info = E.Info(st.t);
                if (info == null) continue;
                rowTicker[i] = st.t;
                var t = st.t;
                // строка списка — как «Object Title / Description + цена» в референсе
                var row = Ui.Card(Content, Pal.Card, 10, 0);
                row.Orientation = Orientation.Horizontal;
                row.SetGravity(GravityFlags.CenterVertical);
                row.Clickable = true;
                row.Click += (s, e) => { selected = t; Sync(); ScrollTop(); };
                Ui.Badge(row, info.Own != null ? Icons.Of(GameData.Industries[info.Own.ind].Id) : Icons.Of(st.t), 44);
                var gap = new View(Ui.Ctx);
                Ui.Add(row, gap, Ui.Dp(12), 1);
                var stack = Ui.Stack(row);
                Ui.Label(stack, st.t + (info.Own != null ? "  ★ ваша" : ""), 16, Pal.Text, true);
                Ui.Label(stack, info.Name, 12, Pal.Muted);
                rowPrice[i] = Ui.Label(row, "", 15, Pal.Text, true, GravityFlags.End | GravityFlags.CenterVertical);
                rowViews[i] = row;
            }
        }

        long BuyQty() { return qty > 0 ? qty : E.MaxBuyShares(selected); }
        long SellQty() { var st = E.Stock(selected); return qty > 0 ? qty : st.own; }

        static double Change(StockState st)
        {
            double base0 = st.h.Count > 0 ? st.h[0] : st.p;
            return st.p / base0 - 1;
        }

        void SyncStocks()
        {
            var st = E.Stock(selected);
            var info = E.Info(selected);
            Ui.Set(selTitle, info.Name + " · " + st.t);
            double ch = Change(st);
            Ui.Set(selPrice, new Rich().T(Fmt.Money(st.p) + "  ").Small(Fmt.Pct(ch) + " за 5 мин", Ui.Sign(ch)));
            chart.SetData(st.h, Ui.Sign(ch));

            var r = new Rich().T("У вас: " + st.own + " шт");
            if (st.own > 0)
            {
                double pl = (st.p - st.avg) * st.own;
                r.T(" · ср. цена " + Fmt.Money(st.avg) + " · ").C(Ui.Signed(pl), Ui.Sign(pl));
            }
            if (info.Div > 0) r.N().T("Дивиденды: " + Fmt.Pct(info.Div, false) + " от цены каждую минуту");
            if (info.Own != null) r.N().T("Ваша компания: доход от неё пропорционален вашей доле (" + Fmt.Pct(E.CoOwnFrac(info.Own), false) + ")");
            Ui.Set(selOwn, r);

            for (int i = 0; i < Qtys.Length; i++) qtyBtns[i].SetColor(Qtys[i] == qty ? Pal.Green : Pal.Btn);
            long bq = BuyQty(), sq = System.Math.Min(SellQty(), st.own);
            buyBtn.Text = "Купить " + bq + "\n" + Fmt.Money(bq * st.p * (1 + GameData.TradeFee));
            buyBtn.Enabled = bq > 0 && E.MaxBuyShares(selected) >= bq;
            sellBtn.Text = "Продать " + sq + "\n" + Fmt.Money(sq * st.p);
            sellBtn.Enabled = st.own > 0;

            bool repaint = paintedSel != selected;
            paintedSel = selected;
            for (int i = 0; i < rowTicker.Length; i++)
            {
                if (rowTicker[i] == null) continue;
                var s = E.Stock(rowTicker[i]);
                double c = Change(s);
                Ui.Set(rowPrice[i], new Rich().T(Fmt.Money(s.p)).N().Small(Fmt.Pct(c), Ui.Sign(c)));
                if (repaint) rowViews[i].Background = Ui.Round(rowTicker[i] == selected ? Pal.Line : Pal.Card, 12);
            }
        }

        // ---------------- Опционы ----------------
        void BuildOptions()
        {
            var card = Cell();
            Ui.Subtitle(card, "Купить опцион");
            var help = Ui.Body(card, 12);
            help.Text = "Call зарабатывает, если цена к экспирации выше страйка, Put — если ниже. " +
                        "1 контракт = " + GameData.OptSize + " акций. Убыток ограничен премией.";

            optTicker = Ui.Button(card, "", Pal.Btn, NextTicker, 15, 50);

            var trow = Ui.Row(card, 50, 8);
            optCallBtn = Ui.Button(trow, "CALL (рост)", Pal.Btn, () => { optCall = true; Sync(); }, 14, 0);
            optPutBtn = Ui.Button(trow, "PUT (падение)", Pal.Btn, () => { optCall = false; Sync(); }, 14, 0);

            optStrikeBtn = Ui.Button(card, "", Pal.Btn, () => { optStrike = (optStrike + 1) % GameData.OptStrikes.Length; Sync(); }, 14, 50);

            var erow = Ui.Row(card, 46, 8);
            optExpBtns = new Btn[GameData.OptExpiries.Length];
            for (int i = 0; i < optExpBtns.Length; i++)
            {
                int k = i;
                optExpBtns[i] = Ui.Button(erow, Fmt.Time(GameData.OptExpiries[i]), Pal.Btn, () => { optExp = k; Sync(); }, 14, 0);
            }

            var qrow = Ui.Row(card, 46, 6);
            Ui.Button(qrow, "-10", Pal.Btn, () => { optQty = System.Math.Max(1, optQty - 10); Sync(); }, 14, 0);
            Ui.Button(qrow, "-1", Pal.Btn, () => { optQty = System.Math.Max(1, optQty - 1); Sync(); }, 14, 0);
            optQtyText = Ui.Label(null, "", 15, Pal.Text, true, GravityFlags.Center);
            Ui.Add(qrow, optQtyText, 0, ViewGroup.LayoutParams.MatchParent, 1.4f);
            Ui.Button(qrow, "+1", Pal.Btn, () => { optQty++; Sync(); }, 14, 0);
            Ui.Button(qrow, "+10", Pal.Btn, () => { optQty += 10; Sync(); }, 14, 0);

            optQuote = Ui.Body(card, 14);
            optBuy = Ui.Button(card, "", Pal.Gold, BuyOption, 16, 56);

            var pc = Cell();
            Ui.Subtitle(pc, "Открытые позиции");
            int n = S.options.Count;
            posText = new TextView[n];
            posClose = new Btn[n];
            posIds = new int[n];
            if (n == 0) Ui.Body(pc, 14).Text = "Нет открытых опционов";
            for (int i = 0; i < n; i++)
            {
                var o = S.options[i];
                posIds[i] = o.id;
                var cell = Ui.Card(pc, Pal.CardAlt, 10, 8);
                posText[i] = Ui.Body(cell, 13);
                int id = o.id;
                posClose[i] = Ui.Button(cell, "", Pal.Blue, () => UI.Try(E.CloseOption(id), "Позиция уже закрыта"), 13, 46);
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
            optTicker.Text = "Акция: " + st.t + " · " + Fmt.Money(st.p) + "   ⇄";
            optCallBtn.SetColor(optCall ? Pal.Green : Pal.Btn);
            optPutBtn.SetColor(!optCall ? Pal.Red : Pal.Btn);
            double k = GameData.OptStrikes[optStrike];
            var q = E.Quote(selected, optCall, k, GameData.OptExpiries[optExp]);
            optStrikeBtn.Text = "Страйк: " + Fmt.Money(q.Strike) + " (" + Fmt.Pct(k - 1) + ")   ⇄";
            for (int i = 0; i < optExpBtns.Length; i++) optExpBtns[i].SetColor(i == optExp ? Pal.Green : Pal.Btn);
            Ui.Set(optQtyText, optQty + " контр.");

            double total = q.Premium * optQty;
            double be = optCall ? q.Strike + q.Premium / GameData.OptSize : q.Strike - q.Premium / GameData.OptSize;
            Ui.Set(optQuote,
                "Премия за контракт: " + Fmt.Money(q.Premium) + "\n" +
                "Точка безубыточности: " + Fmt.Money(be) + "\n" +
                "Экспирация через " + Fmt.Time(GameData.OptExpiries[optExp]));
            optBuy.Text = "Купить за " + Fmt.Money(total);
            optBuy.Enabled = S.money >= total;

            for (int i = 0; i < posIds.Length; i++)
            {
                var o = S.options.Find(x => x.id == posIds[i]);
                if (o == null) continue;
                double v = E.OptionValue(o);
                double pl = v * (1 - GameData.OptSpread) - o.paid;
                var r = new Rich();
                r.B((o.call ? "CALL " : "PUT ") + o.t, o.call ? Pal.Green : Pal.Red)
                 .T(" · страйк " + Fmt.Money(o.strike) + " · " + o.qty + " контр.").N()
                 .T("Цена акции: " + Fmt.Money(E.Stock(o.t).p) + " · осталось " + Fmt.Time(o.expiry - S.t)).N()
                 .T("Стоимость: " + Fmt.Money(v) + " · ").C(Ui.Signed(pl), Ui.Sign(pl));
                Ui.Set(posText[i], r);
                posClose[i].Text = "Закрыть досрочно за " + Fmt.Money(v * (1 - GameData.OptSpread));
            }
        }

        // ---------------- Портфель ----------------
        void BuildPortfolio()
        {
            var card = Cell();
            Ui.Subtitle(card, "Ваш портфель");
            portText = Ui.Body(card, 14);
        }

        void SyncPortfolio()
        {
            double total = 0, pl = 0, ov = 0;
            foreach (var st in S.stocks) { total += st.own * st.p; pl += (st.p - st.avg) * st.own; }
            foreach (var o in S.options) ov += E.OptionValue(o);

            var r = new Rich();
            r.T("Акции: ").B(Fmt.Money(total), Pal.Gold).N();
            r.T("Прибыль/убыток: ").C(Ui.Signed(pl), Ui.Sign(pl)).N();
            r.T("Опционы: " + Fmt.Money(ov) + " (" + S.options.Count + " поз.)").N();
            bool any = false;
            foreach (var st in S.stocks)
            {
                if (st.own == 0) continue;
                any = true;
                double p = (st.p - st.avg) * st.own;
                r.N().B(st.t, Pal.Text).T(": " + st.own + " шт · " + Fmt.Money(st.own * st.p) + " · ").C(Ui.Signed(p), Ui.Sign(p));
            }
            if (!any) r.N().T("Пока нет акций. Купите их во вкладке «Акции».");
            Ui.Set(portText, r);
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
