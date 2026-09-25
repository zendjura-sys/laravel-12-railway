using Android.Widget;

namespace Tycoon.Droid
{
    // Банк: вклад под процент и кредит под залог капитала
    public class BankScreen : GameScreen
    {
        public BankScreen(Shell ui) : base(ui) { }
        public override string Title { get { return "Банк"; } }

        TextView depText, loanText;
        Btn dep25, depAll, wd50, wdAll, loan25, loanMax, repay50, repayAll;

        protected override void Build()
        {
            var dc = Cell();
            Ui.Subtitle(dc, "Вклад");
            depText = Ui.Body(dc, 14);
            var r1 = Ui.Row(dc, 52, 8);
            dep25 = Ui.Button(r1, "Внести 25%", Pal.Green, () => UI.Try(E.Deposit(S.money * 0.25), "Нет денег"), 15, 0);
            depAll = Ui.Button(r1, "Внести всё", Pal.Green, () => UI.Try(E.Deposit(S.money), "Нет денег"), 15, 0);
            var r2 = Ui.Row(dc, 52, 8);
            wd50 = Ui.Button(r2, "Снять 50%", Pal.Blue, () => UI.Try(E.Withdraw(S.bankDep * 0.5), "Вклад пуст"), 15, 0);
            wdAll = Ui.Button(r2, "Снять всё", Pal.Blue, () => UI.Try(E.Withdraw(S.bankDep), "Вклад пуст"), 15, 0);

            var lc = Cell();
            Ui.Subtitle(lc, "Кредит");
            loanText = Ui.Body(lc, 14);
            var r3 = Ui.Row(lc, 54, 8);
            loan25 = Ui.Button(r3, "", Pal.Gold, () => UI.Try(E.TakeLoan((E.LoanLimit - S.bankLoan) * 0.25), "Лимит исчерпан"), 14, 0);
            loanMax = Ui.Button(r3, "", Pal.Gold, () => UI.Try(E.TakeLoan(E.LoanLimit - S.bankLoan), "Лимит исчерпан"), 14, 0);
            var r4 = Ui.Row(lc, 52, 8);
            repay50 = Ui.Button(r4, "Погасить 50%", Pal.Blue, () => UI.Try(E.Repay(S.bankLoan * 0.5), "Нечем гасить"), 15, 0);
            repayAll = Ui.Button(r4, "Погасить всё", Pal.Blue, () => UI.Try(E.Repay(S.bankLoan), "Нечем гасить"), 15, 0);

            Ui.Body(Content, 12).Text = "Совет: кредит выгоден, если бизнес окупится быстрее, чем растут проценты. " +
                                        "Вклад — безопасное место для денег, которые пока не на что потратить.";
        }

        protected override void Sync()
        {
            double depPerMin = S.bankDep * GameData.DepositRate * 60;
            Ui.Set(depText, new Rich()
                .T("На вкладе: ").B(Fmt.Money(S.bankDep), Pal.Gold).N()
                .T("Ставка: " + Fmt.Pct(GameData.DepositRate * 60, false) + " в минуту · +" + Fmt.Money(depPerMin) + "/мин"));
            dep25.Enabled = depAll.Enabled = S.money > 0.01;
            wd50.Enabled = wdAll.Enabled = S.bankDep > 0.01;

            double limit = E.LoanLimit, free = System.Math.Max(0, limit - S.bankLoan);
            Ui.Set(loanText, new Rich()
                .T("Долг: ").C(Fmt.Money(S.bankLoan), S.bankLoan > 0 ? Pal.Red : Pal.Text).N()
                .T("Лимит: " + Fmt.Money(limit) + " (50% капитала) · доступно " + Fmt.Money(free)).N()
                .T("Ставка: " + Fmt.Pct(GameData.LoanRate * 60, false) + " в минуту"));
            loan25.Text = "Взять 25%\n" + Fmt.Money(free * 0.25);
            loanMax.Text = "Взять максимум\n" + Fmt.Money(free);
            loan25.Enabled = loanMax.Enabled = free > 1;
            repay50.Enabled = repayAll.Enabled = S.bankLoan > 0.01 && S.money > 0.01;
        }
    }
}
