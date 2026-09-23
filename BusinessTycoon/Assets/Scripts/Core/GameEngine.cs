using System;
using System.Collections.Generic;

// Игровая логика: производство, рынок, биржа, опционы, компании, банк, престиж.
namespace Tycoon
{
    public class MatCost
    {
        public int Res;
        public double Count;
    }

    public class UpgradeCost
    {
        public double Money;
        public MatCost Mat;
    }

    // Описание бумаги на бирже: либо статическая компания, либо собственная после IPO
    public class StockInfo
    {
        public string Ticker, Name, Color;
        public double Vol, Drift, Div;
        public Company Own;
    }

    public class OptionQuote
    {
        public double Strike;
        public double Premium;   // за один контракт
    }

    public class OfflineReport
    {
        public double Seconds;
        public double Money;
        public double[] Res;
    }

    public class GameEngine
    {
        public GameState S;
        public readonly Random Rng;

        // Сглаженные показатели для интерфейса (не сохраняются)
        public double IncomePerSec;
        public readonly double[] ResRates = new double[GameData.Resources.Length];
        public readonly string[] BizIssue = new string[GameData.Businesses.Length];
        // Поднимается, когда меняется структура экранов (открылся бизнес и т.п.)
        public bool StructureDirty;

        double stockAcc, histAcc, divAcc;

        public GameEngine(GameState state, int seed = 0)
        {
            S = state ?? GameState.Create();
            S.Normalize();
            Rng = seed == 0 ? new Random() : new Random(seed);
        }

        // ---------- Утилиты ----------
        public double Gauss()
        {
            double u = 1 - Rng.NextDouble(), v = Rng.NextDouble();
            return Math.Sqrt(-2 * Math.Log(u)) * Math.Cos(2 * Math.PI * v);
        }
        double Rand(double a, double b) { return a + Rng.NextDouble() * (b - a); }
        T Pick<T>(IList<T> list) { return list[Rng.Next(list.Count)]; }

        static double Ncdf(double x)
        {
            double t = 1 / (1 + 0.2316419 * Math.Abs(x));
            double d = 0.3989423 * Math.Exp(-x * x / 2);
            double p = d * t * (0.3193815 + t * (-0.3565638 + t * (1.781478 + t * (-1.821256 + t * 1.330274))));
            return x > 0 ? 1 - p : p;
        }

        public void AddNews(string text, int kind = 0)
        {
            S.news.Insert(0, new NewsItem { text = text, kind = kind });
            if (S.news.Count > 30) S.news.RemoveRange(30, S.news.Count - 30);
        }

        public void Earn(double x) { S.money += x; S.runEarned += x; S.totalEarned += x; }

        bool Spend(double x)
        {
            if (S.money + 1e-9 < x) return false;
            S.money -= x;
            return true;
        }

        // ---------- Множители ----------
        public double RepMult { get { return 1 + 0.1 * S.rep; } }

        public static double MilestoneMult(int n)
        {
            double m = 1;
            foreach (var x in GameData.Milestones) if (n >= x) m *= 2;
            return m;
        }

        public static int NextMilestone(int n)
        {
            foreach (var x in GameData.Milestones) if (x > n) return x;
            return -1;
        }

        public double BizMult(int i)
        {
            var b = S.biz[i];
            return Math.Pow(2, b.lvl) * MilestoneMult(b.n) * RepMult;
        }

        // ---------- Материалы ----------
        // Четверть стоимости улучшения оплачивается сырьём: выбирается самый дорогой ресурс, которого нужно не меньше 10 шт.
        public static MatCost MatFor(double money)
        {
            double v = money * 0.25;
            int m = GameData.MatOrder[0];
            foreach (var r in GameData.MatOrder) if (GameData.Resources[r].Price * 10 <= v) m = r;
            return new MatCost { Res = m, Count = Math.Ceiling(v / GameData.Resources[m].Price) };
        }

        public bool HasMat(MatCost m) { return S.res[m.Res] >= m.Count; }

        bool Pay(UpgradeCost c)
        {
            if (S.money < c.Money || !HasMat(c.Mat)) return false;
            S.money -= c.Money;
            S.res[c.Mat.Res] -= c.Mat.Count;
            return true;
        }

        public bool CanPay(UpgradeCost c) { return S.money >= c.Money && HasMat(c.Mat); }

        // ---------- Бизнесы ----------
        public double BizCost(int i, int n)
        {
            var d = GameData.Businesses[i];
            int own = S.biz[i].n;
            return d.Cost * Math.Pow(d.Growth, own) * (Math.Pow(d.Growth, n) - 1) / (d.Growth - 1);
        }

        public int BizMaxAffordable(int i)
        {
            var d = GameData.Businesses[i];
            double a = d.Cost * Math.Pow(d.Growth, S.biz[i].n);
            return Math.Max(0, (int)Math.Floor(Math.Log(S.money * (d.Growth - 1) / a + 1) / Math.Log(d.Growth)));
        }

        // mode: 1, 10, 100 или 0 = максимум
        public int BuyAmount(int i, int mode) { return mode > 0 ? mode : Math.Max(1, BizMaxAffordable(i)); }

        public bool BuyBiz(int i, int mode)
        {
            int n = BuyAmount(i, mode);
            if (!Spend(BizCost(i, n))) return false;
            S.biz[i].n += n;
            S.biz[i].seen = true;
            return true;
        }

        public UpgradeCost BizUpgradeCost(int i)
        {
            double money = GameData.Businesses[i].Cost * 25 * Math.Pow(6, S.biz[i].lvl);
            return new UpgradeCost { Money = money, Mat = MatFor(money) };
        }

        public bool UpgradeBiz(int i)
        {
            var b = S.biz[i];
            if (b.n < 1 || b.lvl >= GameData.MaxBizLevel) return false;
            if (!Pay(BizUpgradeCost(i))) return false;
            b.lvl++;
            return true;
        }

        public double BizValue(int i)
        {
            var d = GameData.Businesses[i];
            return d.Cost * (Math.Pow(d.Growth, S.biz[i].n) - 1) / (d.Growth - 1) * 0.5;
        }

        // ---------- Склад и рынок сырья ----------
        public double Capacity { get { return Math.Floor(100 * Math.Pow(2.2, S.storeLvl)); } }

        public UpgradeCost StoreUpgradeCost()
        {
            double money = 200 * Math.Pow(3.5, S.storeLvl);
            return new UpgradeCost { Money = money, Mat = MatFor(money) };
        }

        public bool UpgradeStore()
        {
            if (!Pay(StoreUpgradeCost())) return false;
            S.storeLvl++;
            return true;
        }

        public double ResPrice(int r) { return GameData.Resources[r].Price * S.mkt[r]; }

        public double SellRes(int r, double frac)
        {
            double q = S.res[r] * frac;
            if (q <= 0) return 0;
            double v = q * ResPrice(r);
            S.res[r] -= q;
            Earn(v);
            // крупная продажа немного давит на цену
            S.mkt[r] = Math.Max(0.4, S.mkt[r] * (1 - Math.Min(0.05, q / (Capacity * 20))));
            return v;
        }

        public bool ResVisible(int r)
        {
            if (S.res[r] > 0.001) return true;
            for (int i = 0; i < GameData.Businesses.Length; i++)
            {
                if (S.biz[i].n == 0) continue;
                var d = GameData.Businesses[i];
                if (d.OutRes == r || Array.IndexOf(d.InRes, r) >= 0) return true;
            }
            return false;
        }

        // ---------- Клик ----------
        public double ClickValue { get { return Math.Pow(1.5, S.clickLvl) + IncomePerSec * Math.Min(0.5, 0.01 * S.clickLvl); } }
        public double ClickUpgradeCost { get { return 25 * Math.Pow(2.4, S.clickLvl); } }

        public double Click()
        {
            double v = ClickValue;
            Earn(v);
            S.clicks++;
            return v;
        }

        public bool UpgradeClick()
        {
            if (!Spend(ClickUpgradeCost)) return false;
            S.clickLvl++;
            return true;
        }

        // ---------- Собственные компании ----------
        public double CoRevenue(Company c)
        {
            var ind = GameData.Industries[c.ind];
            return ind.Base * c.scale * c.staff * (1 + 0.3 * c.mkt) * Math.Pow(2, c.rnd) * RepMult;
        }

        public double CoValuation(Company c) { return CoRevenue(c) * 300; }
        public double CoOwnFrac(Company c) { return c.pub ? (double)Stock(c.ticker).own / GameData.CompanyShares : 1; }
        public double CoHireCost(Company c) { return GameData.Industries[c.ind].Hire * c.scale * Math.Pow(1.15, c.staff); }
        public double CoMarketingCost(Company c) { return GameData.Industries[c.ind].Hire * c.scale * 8 * Math.Pow(1.7, c.mkt); }

        public UpgradeCost CoRndCost(Company c)
        {
            double money = GameData.Industries[c.ind].Hire * c.scale * 40 * Math.Pow(4, c.rnd);
            return new UpgradeCost { Money = money, Mat = MatFor(money) };
        }

        public bool CanFoundCompany { get { return S.companies.Count < GameData.CompanyFoundCost.Length; } }
        public double FoundCost { get { return CanFoundCompany ? GameData.CompanyFoundCost[S.companies.Count] : 0; } }
        public double IpoMin(Company c) { return GameData.IpoMinValuation * c.scale; }

        public Company FindCompany(int id) { return S.companies.Find(c => c.id == id); }

        // Возвращает текст ошибки или null при успехе
        public string FoundCompany(string name, int ind)
        {
            if (!CanFoundCompany) return "Достигнут лимит компаний";
            name = (name ?? "").Trim();
            if (name.Length > 24) name = name.Substring(0, 24);
            if (name.Length == 0) return "Введите название компании";
            if (ind < 0 || ind >= GameData.Industries.Length) return "Выберите отрасль";
            int idx = S.companies.Count;
            if (!Spend(GameData.CompanyFoundCost[idx])) return "Недостаточно средств";
            S.companies.Add(new Company
            {
                id = S.coId++, name = name, ind = ind, scale = GameData.CompanyScale[idx], staff = 1,
            });
            AddNews("Вы основали компанию «" + name + "»", 1);
            return null;
        }

        public bool CoHire(Company c) { if (!Spend(CoHireCost(c))) return false; c.staff++; return true; }
        public bool CoMarketing(Company c) { if (!Spend(CoMarketingCost(c))) return false; c.mkt++; return true; }
        public bool CoRnd(Company c) { if (!Pay(CoRndCost(c))) return false; c.rnd++; return true; }

        static readonly Dictionary<char, string> Translit = new Dictionary<char, string>
        {
            {'а',"A"},{'б',"B"},{'в',"V"},{'г',"G"},{'д',"D"},{'е',"E"},{'ё',"E"},{'ж',"Z"},{'з',"Z"},{'и',"I"},{'й',"I"},
            {'к',"K"},{'л',"L"},{'м',"M"},{'н',"N"},{'о',"O"},{'п',"P"},{'р',"R"},{'с',"S"},{'т',"T"},{'у',"U"},{'ф',"F"},
            {'х',"H"},{'ц',"C"},{'ч',"C"},{'ш',"S"},{'щ',"S"},{'ы',"Y"},{'э',"E"},{'ю',"U"},{'я',"A"},
        };

        public string MakeTicker(string name)
        {
            var sb = new System.Text.StringBuilder();
            foreach (var ch0 in name.ToLowerInvariant())
            {
                string s;
                if (ch0 >= 'a' && ch0 <= 'z') sb.Append(char.ToUpperInvariant(ch0));
                else if (Translit.TryGetValue(ch0, out s)) sb.Append(s);
                if (sb.Length >= 4) break;
            }
            var t = (sb.ToString() + "XXXX").Substring(0, 4);
            var outT = t;
            int i = 1;
            while (Stock(outT) != null) outT = t.Substring(0, 3) + (i++ % 10);
            return outT;
        }

        public bool Ipo(Company c, double pct)
        {
            if (c == null || c.pub) return false;
            double val = CoValuation(c);
            if (val < IpoMin(c)) return false;
            c.pub = true;
            c.ticker = MakeTicker(c.name);
            double price = val / GameData.CompanyShares;
            var st = new StockState
            {
                t = c.ticker, p = price, f = price, p0 = price, avg = price,
                own = (long)Math.Round(GameData.CompanyShares * (1 - pct)),
            };
            st.h.Add(price);
            S.stocks.Add(st);
            double cash = val * pct * 0.93;
            Earn(cash);
            AddNews("IPO! «" + c.name + "» (" + c.ticker + ") вышла на биржу. Привлечено " + Fmt.Money(cash), 1);
            return true;
        }

        public bool SellCompany(Company c)
        {
            if (c == null || c.pub) return false;
            double v = CoValuation(c) * 0.7;
            Earn(v);
            AddNews("Компания «" + c.name + "» продана за " + Fmt.Money(v));
            S.companies.Remove(c);
            return true;
        }

        // ---------- Биржа ----------
        public StockState Stock(string t) { return S.stocks.Find(x => x.t == t); }

        public StockInfo Info(string t)
        {
            foreach (var d in GameData.Stocks)
                if (d.Ticker == t) return new StockInfo { Ticker = t, Name = d.Name, Color = d.Color, Vol = d.Vol, Drift = d.Drift, Div = d.Div };
            var c = S.companies.Find(x => x.ticker == t);
            if (c == null) return null;
            var ind = GameData.Industries[c.ind];
            return new StockInfo { Ticker = t, Name = c.name, Color = ind.Color, Vol = ind.Vol, Own = c };
        }

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
            var st = Stock(t);
            n = Math.Min(n, MaxBuyShares(t));
            if (n <= 0) return false;
            S.money -= n * st.p * (1 + GameData.TradeFee);
            st.avg = (st.avg * st.own + st.p * n) / (st.own + n);
            st.own += n;
            return true;
        }

        public bool SellStock(string t, long n)
        {
            var st = Stock(t);
            n = Math.Min(n, st.own);
            if (n <= 0) return false;
            Earn(n * st.p * (1 - GameData.TradeFee));
            st.own -= n;
            if (st.own == 0) st.avg = 0;
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
            if (qty < 1) return false;
            var q = Quote(t, call, strikeK, T);
            double cost = q.Premium * qty;
            if (!Spend(cost)) return false;
            S.options.Add(new OptionPos { id = S.optId++, t = t, call = call, strike = q.Strike, qty = qty, expiry = S.t + T, paid = cost });
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
            Earn(OptionValue(o) * (1 - GameData.OptSpread));
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
                Earn(v);
                var label = (o.call ? "Call " : "Put ") + o.t + " " + Fmt.Money(o.strike);
                AddNews(v > 0 ? "Опцион " + label + " исполнен: +" + Fmt.Money(v) : "Опцион " + label + " сгорел", v > o.paid ? 1 : -1);
                S.options.RemoveAt(i);
            }
        }

        // ---------- Банк ----------
        public double LoanLimit { get { return Math.Max(1000, (NetWorth() + S.bankLoan) * 0.5); } }

        public bool Deposit(double a) { a = Math.Min(Math.Max(0, a), S.money); S.money -= a; S.bankDep += a; return a > 0; }
        public bool Withdraw(double a) { a = Math.Min(Math.Max(0, a), S.bankDep); S.bankDep -= a; S.money += a; return a > 0; }
        public bool TakeLoan(double a) { a = Math.Min(Math.Max(0, a), Math.Max(0, LoanLimit - S.bankLoan)); S.bankLoan += a; S.money += a; return a > 0; }
        public bool Repay(double a) { a = Math.Min(Math.Max(0, a), Math.Min(S.bankLoan, S.money)); S.bankLoan -= a; S.money -= a; return a > 0; }

        // ---------- События ----------
        static readonly string[] GoodNews = { "{0}: рекордная квартальная прибыль", "{0} получила крупный госконтракт", "Аналитики повысили рейтинг {0}", "{0} запустила прорывной продукт" };
        static readonly string[] BadNews = { "Скандал вокруг руководства {0}", "{0}: внеплановая налоговая проверка", "{0} не выполнила прогноз по прибыли", "Авария на объекте {0}" };
        static readonly string[] ResUp = { "Засуха! Спрос на ресурс «{0}» резко вырос", "Экспортный бум: «{0}» в дефиците", "Госзакупки: «{0}» дорожает" };
        static readonly string[] ResDown = { "Перепроизводство: «{0}» дешевеет", "Импорт обвалил цены: «{0}»", "Склады переполнены: «{0}» никому не нужно" };

        void RandomEvent()
        {
            if (Rng.NextDouble() < 0.6)
            {
                var st = Pick(S.stocks);
                var d = Info(st.t);
                if (d == null) return;
                bool good = Rng.NextDouble() < 0.52;
                double j = Rand(0.05, 0.25) * (good ? 1 : -1);
                st.p *= 1 + j;
                st.f *= 1 + j * 0.5;
                AddNews(string.Format(Pick(good ? GoodNews : BadNews), d.Name) + " (" + st.t + " " + Fmt.Pct(j) + ")", good ? 1 : -1);
            }
            else
            {
                int r = Rng.Next(GameData.Resources.Length);
                bool up = Rng.NextDouble() < 0.5;
                S.mkt[r] = Math.Min(3, Math.Max(0.4, S.mkt[r] * (up ? Rand(1.4, 2) : Rand(0.5, 0.7))));
                AddNews(string.Format(Pick(up ? ResUp : ResDown), GameData.Resources[r].Name), up ? 1 : -1);
            }
        }

        // ---------- Основной шаг симуляции ----------
        public void Tick(double dt, bool offline = false)
        {
            S.t += dt;
            double cap = Capacity;
            double cash = 0;
            var delta = new double[GameData.Resources.Length];

            for (int i = 0; i < GameData.Businesses.Length; i++)
            {
                var d = GameData.Businesses[i];
                var b = S.biz[i];
                BizIssue[i] = null;
                if (b.n == 0) continue;
                double k = b.n * BizMult(i) * dt;
                if (d.IsService) { cash += d.Cash * k; continue; }

                double f = 1;
                string why = null;
                for (int j = 0; j < d.InRes.Length; j++)
                {
                    double x = S.res[d.InRes[j]] / (d.InQ[j] * k);
                    if (x < f) { f = x; why = "Не хватает сырья"; }
                }
                double room = Math.Max(0, cap - S.res[d.OutRes]) / (d.OutQ * k);
                if (room < f) { f = room; why = "Склад полон"; }
                f = Math.Max(0, f);

                for (int j = 0; j < d.InRes.Length; j++)
                {
                    double q = d.InQ[j] * k * f;
                    S.res[d.InRes[j]] -= q;
                    delta[d.InRes[j]] -= q;
                }
                double outQ = d.OutQ * k * f;
                S.res[d.OutRes] += outQ;
                delta[d.OutRes] += outQ;
                if (f < 0.999) BizIssue[i] = why;
            }

            // Автопродажа через торгового агента (комиссия 10%)
            for (int r = 0; r < S.res.Length; r++)
            {
                if (!S.auto[r] || S.res[r] <= 0) continue;
                cash += S.res[r] * ResPrice(r) * 0.9;
                S.res[r] = 0;
            }

            foreach (var c in S.companies) cash += CoRevenue(c) * CoOwnFrac(c) * dt;

            S.bankDep += S.bankDep * GameData.DepositRate * dt;
            S.bankLoan += S.bankLoan * GameData.LoanRate * dt;

            Earn(cash);

            double a = Math.Min(1, dt / 2);
            IncomePerSec += (cash / dt - IncomePerSec) * a;
            for (int r = 0; r < ResRates.Length; r++) ResRates[r] += (delta[r] / dt - ResRates[r]) * a;

            // Рынок сырья: случайное блуждание с возвратом к базовой цене
            for (int r = 0; r < S.mkt.Length; r++)
            {
                double m = S.mkt[r];
                S.mkt[r] = Math.Min(3, Math.Max(0.4, m + (1 - m) * 0.003 * dt + 0.01 * Math.Sqrt(dt) * Gauss()));
            }

            if (offline) return;

            stockAcc += dt;
            while (stockAcc >= 1)
            {
                stockAcc -= 1;
                StepStocks();
                histAcc += 1;
                if (histAcc >= 2) { histAcc = 0; PushHistory(); }
                divAcc += 1;
                if (divAcc >= 60) { divAcc = 0; PayDividends(); }
            }
            SettleOptions();

            S.nextEvent -= dt;
            if (S.nextEvent <= 0) { RandomEvent(); S.nextEvent = Rand(40, 90); }

            for (int i = 0; i < GameData.Businesses.Length; i++)
            {
                if (!S.biz[i].seen && S.money >= GameData.Businesses[i].Cost * 0.6)
                {
                    S.biz[i].seen = true;
                    StructureDirty = true;
                }
            }
        }

        public OfflineReport SimulateOffline(double sec)
        {
            sec = Math.Min(sec, GameData.OfflineCapSeconds);
            var rep = new OfflineReport { Seconds = sec, Res = new double[S.res.Length] };
            if (sec < 1) return rep;
            double m0 = S.money;
            var r0 = (double[])S.res.Clone();
            double step = Math.Max(1, sec / 15000);
            double left = sec;
            while (left > 0)
            {
                double dt = Math.Min(step, left);
                Tick(dt, true);
                left -= dt;
            }
            int steps = (int)Math.Min(Math.Floor(sec), 300);
            for (int i = 0; i < steps; i++) { StepStocks(); if (i % 2 == 1) PushHistory(); }
            for (int i = 0; i < (int)(sec / 60); i++) PayDividends();
            SettleOptions();
            rep.Money = S.money - m0;
            for (int r = 0; r < r0.Length; r++) rep.Res[r] = S.res[r] - r0[r];
            return rep;
        }

        // ---------- Капитал и престиж ----------
        public double NetWorth()
        {
            double w = S.money + S.bankDep - S.bankLoan;
            for (int r = 0; r < S.res.Length; r++) w += S.res[r] * ResPrice(r);
            w += PortfolioValue();
            foreach (var o in S.options) w += OptionValue(o);
            foreach (var c in S.companies) if (!c.pub) w += CoValuation(c);
            for (int i = 0; i < S.biz.Length; i++) w += BizValue(i);
            return w;
        }

        public int RepGain { get { return (int)Math.Floor(Math.Sqrt(S.runEarned / 1e7)); } }

        public bool Prestige()
        {
            int g = RepGain;
            if (g < 1) return false;
            var ns = GameState.Create();
            ns.rep = S.rep + g;
            ns.prestiges = S.prestiges + 1;
            ns.totalEarned = S.totalEarned;
            ns.money = GameData.StartMoney + 500 * ns.rep;
            S = ns;
            IncomePerSec = 0;
            Array.Clear(ResRates, 0, ResRates.Length);
            AddNews("Новая жизнь! Репутация: " + S.rep + " (+" + (S.rep * 10) + "% к доходу)", 1);
            StructureDirty = true;
            return true;
        }
    }
}
