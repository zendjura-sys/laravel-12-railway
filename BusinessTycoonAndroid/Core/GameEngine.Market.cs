using System;

// Биржа: акции (в том числе собственных компаний после IPO), дивиденды, опционы
namespace Tycoon
{
    // Описание бумаги: статическая компания или собственная после IPO
    public class StockInfo
    {
        public string Ticker, Name;
        public Sector Sector;
        public double Vol, Drift, Div;
        public Company Own;
    }

    public class OptionQuote
    {
        public double Strike;
        public double Premium;   // за один контракт
    }

    public partial class GameEngine
    {
        public StockState Stock(string t) { return S.stocks.Find(x => x.t == t); }

        public StockInfo Info(string t)
        {
            foreach (var d in GameData.Stocks)
                if (d.Ticker == t)
                    return new StockInfo { Ticker = t, Name = d.Name, Sector = d.Sector, Vol = d.Vol, Drift = d.Drift, Div = d.Div };
            var c = S.companies.Find(x => x.ticker == t);
            if (c == null) return null;
            var ind = GameData.Industries[c.ind];
            return new StockInfo { Ticker = t, Name = c.name, Vol = ind.Vol, Own = c, Sector = SectorOf(c.ind) };
        }

        static Sector SectorOf(int ind)
        {
            switch (GameData.Industries[ind].Id)
            {
                case "it": return Sector.Tech;
                case "retail":
                case "food": return Sector.Consumer;
                case "energy": return Sector.Energy;
                case "finance": return Sector.Finance;
                default: return Sector.Industry;
            }
        }

        public bool CanTrade { get { return S.level >= GameData.LevelStocks; } }
        public bool CanOptions { get { return S.level >= GameData.LevelOptions; } }

        void StepStocks()
        {
            foreach (var st in S.stocks)
            {
                var d = Info(st.t);
                if (d == null) continue;
                if (d.Own != null) st.f = Math.Max(0.01, CoValuation(d.Own) / GameData.CompanyShares);
                else st.f *= Math.Exp(d.Drift);
                double lp = Math.Log(st.p), lf = Math.Log(st.f);
                st.p = Math.Max(0.01, Math.Exp(lp + GameData.StockTheta * (lf - lp) + d.Vol * Gauss()));
            }
        }

        void PushHistory()
        {
            foreach (var st in S.stocks)
            {
                st.h.Add(st.p);
                if (st.h.Count > 150) st.h.RemoveAt(0);
            }
        }

        public long MaxBuyShares(string t)
        {
            var st = Stock(t);
            long n = (long)Math.Floor(S.money / (st.p * (1 + GameData.TradeFee)));
            if (Info(t).Own != null) n = Math.Min(n, GameData.CompanyShares - st.own);
            return Math.Max(0, n);
        }

        public bool BuyStock(string t, long n)
        {
            if (!CanTrade) return false;
            var st = Stock(t);
            n = Math.Min(n, MaxBuyShares(t));
            if (n <= 0) return false;
            S.money -= n * st.p * (1 + GameData.TradeFee);
            st.avg = (st.avg * st.own + st.p * n) / (st.own + n);
            st.own += n;
            S.trades++;
            AddXp(3);
            return true;
        }

        public bool SellStock(string t, long n)
        {
            var st = Stock(t);
            n = Math.Min(n, st.own);
            if (n <= 0) return false;
            // возврат вложенного — не доход, опыт даёт только прибыль
            double proceeds = n * st.p * (1 - GameData.TradeFee);
            double gain = Math.Max(0, proceeds - n * st.avg);
            S.money += proceeds;
            S.runEarned += gain;
            S.totalEarned += gain;
            AddXp(3 + gain * GameData.XpPerDollar);
            st.own -= n;
            if (st.own == 0) st.avg = 0;
            S.trades++;
            return true;
        }

        void PayDividends()
        {
            foreach (var st in S.stocks)
            {
                if (st.own == 0) continue;
                var d = Info(st.t);
                if (d != null && d.Div > 0) Earn(st.own * st.p * d.Div);
            }
        }

        public double PortfolioValue()
        {
            double v = 0;
            foreach (var st in S.stocks) v += st.own * st.p;
            return v;
        }

        // ---------- Опционы (упрощённый Блэк–Шоулз, r = 0) ----------
        static double Ncdf(double x)
        {
            double t = 1 / (1 + 0.2316419 * Math.Abs(x));
            double d = 0.3989423 * Math.Exp(-x * x / 2);
            double p = d * t * (0.3193815 + t * (-0.3565638 + t * (1.781478 + t * (-1.821256 + t * 1.330274))));
            return x > 0 ? 1 - p : p;
        }

        public static double OptionFair(bool call, double s0, double k, double T, double sig)
        {
            double intr = call ? Math.Max(0, s0 - k) : Math.Max(0, k - s0);
            if (T <= 0) return intr;
            double v = sig * Math.Sqrt(T);
            double d1 = (Math.Log(s0 / k) + v * v / 2) / v, d2 = d1 - v;
            double c = s0 * Ncdf(d1) - k * Ncdf(d2);
            return Math.Max(intr, call ? c : c - s0 + k);
        }

        public static double RoundStrike(double raw)
        {
            double mag = Math.Pow(10, Math.Floor(Math.Log10(raw)) - 2);
            return Math.Max(0.01, Math.Round(raw / mag) * mag);
        }

        public OptionQuote Quote(string t, bool call, double strikeK, double T)
        {
            var st = Stock(t);
            double k = RoundStrike(st.p * strikeK);
            double fair = OptionFair(call, st.p, k, T, Info(t).Vol);
            return new OptionQuote { Strike = k, Premium = fair * (1 + GameData.OptSpread) * GameData.OptSize };
        }

        public bool BuyOption(string t, bool call, double strikeK, double T, int qty)
        {
            if (!CanOptions || qty < 1) return false;
            var q = Quote(t, call, strikeK, T);
            double cost = q.Premium * qty;
            if (!Spend(cost)) return false;
            S.options.Add(new OptionPos { id = S.optId++, t = t, call = call, strike = q.Strike, qty = qty, expiry = S.t + T, paid = cost });
            S.trades++;
            AddXp(5);
            return true;
        }

        public double OptionValue(OptionPos o)
        {
            var st = Stock(o.t);
            var d = Info(o.t);
            if (st == null || d == null) return 0;
            return OptionFair(o.call, st.p, o.strike, Math.Max(0, o.expiry - S.t), d.Vol) * GameData.OptSize * o.qty;
        }

        public bool CloseOption(int id)
        {
            var o = S.options.Find(x => x.id == id);
            if (o == null) return false;
            double v = OptionValue(o) * (1 - GameData.OptSpread);
            S.money += v;
            if (v > o.paid) { S.runEarned += v - o.paid; S.totalEarned += v - o.paid; AddXp((v - o.paid) * GameData.XpPerDollar); }
            S.options.Remove(o);
            return true;
        }

        void SettleOptions()
        {
            for (int i = S.options.Count - 1; i >= 0; i--)
            {
                var o = S.options[i];
                if (S.t < o.expiry) continue;
                double v = OptionValue(o);
                S.money += v;
                if (v > o.paid) { S.runEarned += v - o.paid; S.totalEarned += v - o.paid; AddXp((v - o.paid) * GameData.XpPerDollar); }
                var label = (o.call ? "Call " : "Put ") + o.t + " " + Fmt.Money(o.strike);
                AddNews(v > 0 ? "Опцион " + label + " исполнен: +" + Fmt.Money(v) : "Опцион " + label + " сгорел", v > o.paid ? 1 : -1);
                S.options.RemoveAt(i);
            }
        }
    }
}
