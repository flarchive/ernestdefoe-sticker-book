import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Avatar from 'flarum/common/components/Avatar';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import { call, pushUsers, rarityName, t, user } from '../util';
import { openTrade } from './TradeModal';

/** The member's trades: offers to answer, offers they made, and what happened to recent ones. */
export default class TradeList {
  oninit() {
    this.data = null;
    this.busy = null;
    this.load();
    this.onChange = () => this.load();
    window.addEventListener('sticker-book:changed', this.onChange);
  }

  onremove() {
    window.removeEventListener('sticker-book:changed', this.onChange);
  }

  load() {
    call('GET', '/trades', undefined, true)
      .then((data) => {
        pushUsers(data.users);
        this.data = data;
      })
      .catch(() => (this.data = this.data || { trades: [], stickers: [] }))
      .finally(() => m.redraw());
  }

  view() {
    if (!this.data) return <LoadingIndicator display="block" />;
    const me = Number(app.session.user.id());
    const trades = this.data.trades;
    const incoming = trades.filter((tr) => tr.status === 'pending' && tr.to === me);
    const outgoing = trades.filter((tr) => tr.status === 'pending' && tr.from === me);
    const past = trades.filter((tr) => tr.status !== 'pending');

    return (
      <div className="StickerTrades">
        <div className="StickerTrades-top">
          <p>{t('trades.intro')}</p>
          <Button className="Button Button--primary" icon="fas fa-right-left" onclick={() => openTrade()}>
            {t('trades.new')}
          </Button>
        </div>
        {this.section(t('trades.incoming'), incoming, me, t('trades.none_incoming'))}
        {this.section(t('trades.outgoing'), outgoing, me, t('trades.none_outgoing'))}
        {past.length ? this.section(t('trades.past'), past, me) : null}
      </div>
    );
  }

  section(title, trades, me, empty) {
    return (
      <section className="StickerTrades-section">
        <h3>
          {title} {trades.length ? <span className="StickerTrades-n">{trades.length}</span> : null}
        </h3>
        {trades.length ? <ul>{trades.map((tr) => this.row(tr, me))}</ul> : empty ? <p className="StickerTrades-empty">{empty}</p> : null}
      </section>
    );
  }

  row(tr, me) {
    const incoming = tr.to === me;
    const other = user(incoming ? tr.from : tr.to);
    // What the viewer gives and gets, whichever side they are on.
    const gives = incoming ? tr.take : tr.give;
    const gets = incoming ? tr.give : tr.take;

    return (
      <li className={`StickerTradeRow is-${tr.status}`} key={tr.id}>
        <div className="StickerTradeRow-head">
          <Link href={other ? app.route.user(other) : '#'} className="StickerTradeRow-who">
            <Avatar user={other} /> <strong>{username(other)}</strong>
          </Link>
          <span className="StickerTradeRow-when">{humanTime(tr.createdAt)}</span>
          {tr.status !== 'pending' ? <span className={`StickerTradeRow-status is-${tr.status}`}>{t(`trades.status.${tr.status}`)}</span> : null}
        </div>
        {tr.message ? <p className="StickerTradeRow-message">“{tr.message}”</p> : null}
        <div className="StickerTradeRow-items">
          <div>
            <span className="StickerTradeRow-label">{t('trades.you_give')}</span>
            {this.items(gives)}
          </div>
          <Icon name="fas fa-right-left" className="StickerTradeRow-arrow" />
          <div>
            <span className="StickerTradeRow-label">{t('trades.you_get')}</span>
            {this.items(gets)}
          </div>
        </div>
        {tr.status === 'pending' ? (
          <div className="StickerTradeRow-actions">
            {incoming
              ? [
                  <Button
                    className="Button Button--primary"
                    icon="fas fa-check"
                    loading={this.busy === `${tr.id}accept`}
                    onclick={() => this.act(tr, 'accept')}
                  >
                    {t('trades.accept')}
                  </Button>,
                  <Button className="Button" loading={this.busy === `${tr.id}decline`} onclick={() => this.act(tr, 'decline')}>
                    {t('trades.decline')}
                  </Button>,
                ]
              : [
                  <Button className="Button" loading={this.busy === `${tr.id}cancel`} onclick={() => this.act(tr, 'cancel')}>
                    {t('trades.cancel')}
                  </Button>,
                ]}
          </div>
        ) : null}
      </li>
    );
  }

  items(list) {
    if (!list.length) return <span className="StickerTradeRow-nothing">{t('trades.nothing')}</span>;
    const byId = Object.fromEntries(this.data.stickers.map((s) => [s.id, s]));
    return (
      <span className="StickerTradeRow-stickers">
        {list.map(([id, n]) => {
          const s = byId[id];
          if (!s) return null;
          return (
            <span className={`StickerMini StickerTile--r${s.rarity}`} title={`${s.name} · ${rarityName(s.rarity)}`}>
              <img src={s.small} alt={s.name} />
              {n > 1 ? <span className="StickerMini-n">×{n}</span> : null}
            </span>
          );
        })}
      </span>
    );
  }

  act(tr, action) {
    this.busy = `${tr.id}${action}`;
    call('POST', `/trades/${tr.id}/${action}`)
      .then(() => {
        if (action === 'accept') app.alerts.show({ type: 'success' }, t('trades.accepted_alert'));
        window.dispatchEvent(new CustomEvent('sticker-book:changed'));
      })
      .catch(() => this.load())
      .finally(() => {
        this.busy = null;
        m.redraw();
      });
  }
}
