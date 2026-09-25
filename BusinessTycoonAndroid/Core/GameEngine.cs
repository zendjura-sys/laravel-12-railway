using System;
using System.Collections.Generic;

// Игровая логика: цикл симуляции, уровни, бизнесы, склад, банк, престиж, события.
// Биржа — GameEngine.Market.cs, магазин и компании — GameEngine.Companies.cs.
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
        public int Item = -1;      // специальный предмет из магазина (или -1)
        public int ItemCount;
    }

    public class OfflineReport
    {
        public double Seconds;
        public double Money;
        public double[] Res;
        public int Levels;
    }

    public partial class GameEngine
    {
        public GameState S;
        public readonly Random Rng;

        // Сглаженные показатели для интерфейса (не сохраняются)
        public double IncomePerSec;
        public readonly double[] ResRates = new double[GameData.Resources.Length];
        public readonly string[] BizIssue = new string[GameData.Businesses.Length];
        public bool StructureDirty;
        // Сообщения для всплывающих уведомлений (новый уровень и т.п.)
        public readonly Queue<string> Toasts = new Queue<string>();

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

        public void AddNews(string text, int kind = 0)
        {
            S.news.Insert(0, new NewsItem { text = text, kind = kind });
            if (S.news.Count > 30) S.news.RemoveRange(30, S.news.Count - 30);
        }

        // Заработок: деньги + опыт (1 XP за каждые $25)
        public void Earn(double x)
        {
            S.money += x;
            S.runEarned += x;
            S.totalEarned += x;
            if (x > 0) AddXp(x * GameData.XpPerDollar);
        }

        bool Spend(double x)
        {
            if (S.money + 1e-9 < x) return false;
            S.money -= x;
            return true;
        }

        // ---------- Уровни ----------
        public double XpToNext { get { return GameData.XpForLevel(S.level); } }
        public bool IsMaxLevel { get { return S.level >= GameData.MaxLevel; } }

        // Для перехода на следующий уровень нужен предмет из магазина (с 15-го уровня)
        public int[] LevelGate { get { return GameData.LevelGate(S.level); } }
        public bool LevelBlocked { get { return !IsMaxLevel && LevelGate != null && S.xp >= XpToNext; } }
        bool gateNotified;

        public void AddXp(double xp)
        {
            if (IsMaxLevel) return;
            S.xp += xp;
            while (!IsMaxLevel && S.xp >= XpToNext)
            {
                if (LevelGate != null)
                {
                    // опыт копится до порога, дальше — только с предметом из магазина
                    S.xp = XpToNext;
                    if (!gateNotified)
                    {
                        gateNotified = true;
                        var g = LevelGate;
                        Toasts.Enqueue("Для уровня " + (S.level + 1) + " нужен «" + GameData.Items[g[0]].Name + "» × " + g[1]);
                    }
                    return;
                }
                S.xp -= XpToNext;
                LevelUp();
            }
            if (IsMaxLevel) S.xp = 0;
        }

        void LevelUp()
        {
            S.level++;
            gateNotified = false;
            double reward = GameData.LevelReward(S.level);
            S.money += reward;
            AddNews("Новый уровень " + S.level + "! Бонус " + Fmt.Money(reward), 1);
            Toasts.Enqueue("Уровень " + S.level + "! +" + Fmt.Money(reward));
            StructureDirty = true;
        }

        // Повышение уровня с использованием предметов из магазина
        public string PromoteLevel()
        {
            var g = LevelGate;
            if (g == null) return "Повышение не требуется";
            if (S.xp < XpToNext) return "Недостаточно опыта";
            if (S.items[g[0]] < g[1]) return "Нужно «" + GameData.Items[g[0]].Name + "» × " + g[1] + " (в магазине)";
            S.items[g[0]] -= g[1];
            S.xp = 0;
            LevelUp();
            return null;
        }

        public bool HasLevel(int lvl) { return S.level >= lvl; }

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

        // ---------- Материалы для улучшений ----------
        // Четверть стоимости оплачивается сырьём: самый дорогой ресурс, которого нужно ≥ 10 шт.
        public static MatCost MatFor(double money)
        {
            double v = money * 0.25;
            int m = GameData.MatOrder[0];
            foreach (var r in GameData.MatOrder) if (GameData.Resources[r].Price * 10 <= v) m = r;
            return new MatCost { Res = m, Count = Math.Ceiling(v / GameData.Resources[m].Price) };
        }

        public bool HasMat(MatCost m) { return S.res[m.Res] >= m.Count; }
        public bool HasItems(UpgradeCost c) { return c.Item < 0 || S.items[c.Item] >= c.ItemCount; }
        public bool CanPay(UpgradeCost c) { return S.money >= c.Money && HasMat(c.Mat) && HasItems(c); }

        bool Pay(UpgradeCost c)
        {
            if (!CanPay(c)) return false;
            S.money -= c.Money;
            S.res[c.Mat.Res] -= c.Mat.Count;
            if (c.Item >= 0) S.items[c.Item] -= c.ItemCount;
            return true;
        }

        // ---------- Бизнесы ----------
        public bool BizUnlocked(int i) { return S.level >= GameData.Businesses[i].ReqLevel; }

        public double BizCost(int i, int n)
        {
            var d = GameData.Businesses[i];
            return d.Cost * Math.Pow(d.Growth, S.biz[i].n) * (Math.Pow(d.Growth, n) - 1) / (d.Growth - 1);
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
            if (!BizUnlocked(i)) return false;
            int n = BuyAmount(i, mode);
            if (!Spend(BizCost(i, n))) return false;
            S.biz[i].n += n;
            S.biz[i].seen = true;
            AddXp(2 * n);
            return true;
        }

        public UpgradeCost BizUpgradeCost(int i)
        {
            int lvl = S.biz[i].lvl;
            double money = GameData.Businesses[i].Cost * 25 * Math.Pow(6, lvl);
            var c = new UpgradeCost { Money = money, Mat = MatFor(money) };
            if (lvl >= GameData.ModkitFromBizLevel)
            {
                c.Item = GameData.Modkit;
                c.ItemCount = lvl - GameData.ModkitFromBizLevel + 1;
            }
            return c;
        }

        public bool UpgradeBiz(int i)
        {
            var b = S.biz[i];
            if (b.n < 1 || b.lvl >= GameData.MaxBizLevel) return false;
            if (!Pay(BizUpgradeCost(i))) return false;
            b.lvl++;
            AddXp(20 * b.lvl);
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
            AddXp(10 * S.storeLvl);
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
            AddXp(1);
            S.clicks++;
            return v;
        }

        public bool UpgradeClick()
        {
            if (!Spend(ClickUpgradeCost)) return false;
            S.clickLvl++;
            return true;
        }

        // ---------- Банк ----------
        public double LoanLimit
        {
            get { return S.level < GameData.LevelLoans ? 0 : Math.Max(1000, (NetWorth() + S.bankLoan) * 0.5); }
        }

        public bool Deposit(double a)
        {
            if (S.level < GameData.LevelDeposit) return false;
            a = Math.Min(Math.Max(0, a), S.money); S.money -= a; S.bankDep += a; return a > 0;
        }
        public bool Withdraw(double a) { a = Math.Min(Math.Max(0, a), S.bankDep); S.bankDep -= a; S.money += a; return a > 0; }
        public bool TakeLoan(double a) { a = Math.Min(Math.Max(0, a), Math.Max(0, LoanLimit - S.bankLoan)); S.bankLoan += a; S.money += a; return a > 0; }
        public bool Repay(double a) { a = Math.Min(Math.Max(0, a), Math.Min(S.bankLoan, S.money)); S.bankLoan -= a; S.money -= a; return a > 0; }

        // ---------- События ----------
        static readonly string[] GoodNews = { "{0}: рекордная квартальная прибыль", "{0} получила крупный госконтракт", "Аналитики повысили рейтинг {0}", "{0} запустила прорывной продукт", "{0} объявила обратный выкуп акций" };
        static readonly string[] BadNews = { "Скандал вокруг руководства {0}", "{0}: внеплановая налоговая проверка", "{0} не выполнила прогноз по прибыли", "Авария на объекте {0}", "{0} попала под санкции регулятора" };
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

            S.bankDep += S.bankDep * GameData.DepositRate * dt;
            S.bankLoan += S.bankLoan * GameData.LoanRate * dt;

            Earn(cash);
            // Собственные компании: прибыль после зарплат и налогов (может быть и убытком)
            double coNet = TickCompanies(dt);

            double a = Math.Min(1, dt / 2);
            IncomePerSec += ((cash + coNet) / dt - IncomePerSec) * a;
            for (int r = 0; r < ResRates.Length; r++) ResRates[r] += (delta[r] / dt - ResRates[r]) * a;

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
        }

        public OfflineReport SimulateOffline(double sec)
        {
            sec = Math.Min(sec, GameData.OfflineCapSeconds);
            var rep = new OfflineReport { Seconds = sec, Res = new double[S.res.Length] };
            if (sec < 1) return rep;
            double m0 = S.money;
            int l0 = S.level;
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
            rep.Levels = S.level - l0;
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
            for (int i = 0; i < S.items.Length; i++) w += S.items[i] * GameData.Items[i].Price * 0.6;
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
            ns.level = S.level;   // опыт предпринимателя остаётся
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
