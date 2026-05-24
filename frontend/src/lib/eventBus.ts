type EventHandler<T = void> = (payload: T) => void;

export type AppEvents = {
  'upload-modal:open': void;
};

class EventBus<E extends Record<string, unknown>> {
  private handlers: { [K in keyof E]?: Set<EventHandler<E[K]>> } = {};

  on<K extends keyof E>(event: K, handler: EventHandler<E[K]>): () => void {
    if (!this.handlers[event]) {
      this.handlers[event] = new Set();
    }
    this.handlers[event]!.add(handler);

    return () => {
      this.handlers[event]!.delete(handler);
    };
  }

  off<K extends keyof E>(event: K, handler: EventHandler<E[K]>): void {
    this.handlers[event]?.delete(handler);
  }

  emit<K extends keyof E>(event: K, payload: E[K]): void {
    this.handlers[event]?.forEach((handler) => handler(payload));
  }
}

export const eventBus = new EventBus<AppEvents>();
