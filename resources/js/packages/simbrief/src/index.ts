export type ProviderFieldValue = string | number | null | undefined;
export type ProviderFields = Record<string, ProviderFieldValue>;

export interface ProviderSubmission {
  fields: Record<string, string>;
  providerUrl: string;
}

export interface ProviderAttemptTransport {
  apiCodeUrl: string;
  headers: HeadersInit;
  pollUrl: string;
  request?: typeof fetch;
}

export interface ProviderPollResponse {
  briefingUrl?: string;
  message?: string;
  type?: string;
}

export interface ProviderPollOptions {
  intervalMs?: number;
  maxAttempts?: number;
  onAttempt?: (attempt: number) => void;
}

interface ApiCodeResponse {
  apiCode: string;
  providerUrl: string;
}

export function createProviderSubmission(
  providerUrl: string,
  providerFields: ProviderFields,
  overrides: ProviderFields,
  apiCode: string,
  locationHref: string,
  timestamp: number,
): ProviderSubmission {
  const fields = Object.fromEntries(
    Object.entries({ ...providerFields, ...overrides }).flatMap(([key, value]) =>
      value === null || value === undefined ? [] : [[key, String(value)]],
    ),
  );

  return {
    providerUrl,
    fields: {
      ...fields,
      apicode: apiCode,
      outputpage: locationHref.replace("http://", ""),
      timestamp: String(timestamp),
    },
  };
}

export function submitProviderGet(submission: ProviderSubmission, target: string | Window): void {
  const targetDocument = typeof target === "string" ? document : target.document;
  const form = targetDocument.createElement("form");
  form.method = "get";
  form.action = submission.providerUrl;
  form.target = typeof target === "string" ? target : "_self";

  for (const [name, value] of Object.entries(submission.fields)) {
    const input = targetDocument.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = value;
    form.append(input);
  }

  targetDocument.body.append(form);
  form.submit();
  form.remove();
}

export function reserveProviderPopup(name: string): Window | null {
  const width = Math.min(600, window.screen.availWidth);
  const height = Math.min(315, window.screen.availHeight);
  const left = Math.round(window.screenX + Math.max(0, (window.outerWidth - width) / 2));
  const top = Math.round(window.screenY + Math.max(0, (window.outerHeight - height) / 2));

  return window.open(
    "about:blank",
    name,
    `popup=yes,width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`,
  );
}

export async function requestProviderSubmission(
  transport: ProviderAttemptTransport,
  providerFields: ProviderFields,
  overrides: ProviderFields,
  locationHref: string,
): Promise<ProviderSubmission> {
  const fields = { ...providerFields, ...overrides };
  const outputPage = locationHref.replace("http://", "");
  const timestamp = Math.round(Date.now() / 1000);
  const apiRequest = [fields.orig, fields.dest, fields.type, timestamp, outputPage].join("");
  const response = await (transport.request ?? fetch)(transport.apiCodeUrl, {
    method: "POST",
    headers: transport.headers,
    body: JSON.stringify({ apiRequest }),
  });

  if (!response.ok) throw new Error("SimBrief could not prepare this flight plan.");
  const result = (await response.json()) as ApiCodeResponse;
  return createProviderSubmission(
    result.providerUrl,
    providerFields,
    overrides,
    result.apiCode,
    locationHref,
    timestamp,
  );
}

function delay(milliseconds: number): Promise<void> {
  return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

export async function pollProviderAttempt(
  transport: ProviderAttemptTransport,
  options: ProviderPollOptions = {},
): Promise<ProviderPollResponse> {
  const maxAttempts = options.maxAttempts ?? 60;
  const intervalMs = options.intervalMs ?? 3_000;

  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    options.onAttempt?.(attempt);
    const response = await (transport.request ?? fetch)(transport.pollUrl, {
      method: "POST",
      headers: transport.headers,
    });
    const result = (await response.json()) as ProviderPollResponse;

    if (response.ok && result.briefingUrl) return result;
    if (response.status !== 409 || result.type !== "ofp-not-ready") {
      throw new Error(result.message ?? "The SimBrief briefing could not be checked.");
    }
    if (attempt < maxAttempts) await delay(intervalMs);
  }

  throw new Error(
    "The SimBrief flight plan was not ready. You can try again without losing your planning context.",
  );
}
