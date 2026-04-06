import { DeleteOutlined, InboxOutlined, LoadingOutlined } from "@ant-design/icons";
import { Button, Upload } from "antd";

const MAX_LOGO_BYTES = 2 * 1024 * 1024;
const ALLOWED_LOGO_TYPES = ["image/jpeg", "image/png", "image/webp"];

export default function CompanyLogoUploader({
    apiFetch,
    orgId,
    logoUrl,
    disabled = false,
    onSettingsChange,
    onError,
    onSuccess,
    onBusyChange,
}) {
    const endpoint = `/kbs/v1/settings/company-logo?org_id=${encodeURIComponent(String(orgId || ""))}`;

    const handleBeforeUpload = (file) => {
        if (!ALLOWED_LOGO_TYPES.includes(file.type)) {
            onError?.("Upload a PNG, JPG, or WEBP logo.");
            return Upload.LIST_IGNORE;
        }

        if ((file.size || 0) > MAX_LOGO_BYTES) {
            onError?.("Company logo must be 2 MB or smaller.");
            return Upload.LIST_IGNORE;
        }

        return true;
    };

    const handleUpload = async ({ file, onSuccess: onUploadSuccess, onError: onUploadError }) => {
        if (!orgId) {
            onError?.("Organization context is missing. Reload the page and try again.");
            onUploadError?.(new Error("Missing organization context."));
            return;
        }
        onBusyChange?.(true);
        try {
            const body = new FormData();
            body.append("logo", file);
            const response = await apiFetch(endpoint, {
                method: "POST",
                body,
            });
            onSettingsChange?.({
                logo_url: response?.settings?.logo_url || response?.logo_url || "",
            });
            onSuccess?.("Company logo uploaded.");
            onUploadSuccess?.(response, file);
        } catch (error) {
            const text = error?.message || "Failed to upload the company logo.";
            onError?.(text);
            onUploadError?.(error);
        } finally {
            onBusyChange?.(false);
        }
    };

    const handleRemove = async () => {
        if (!orgId) {
            onError?.("Organization context is missing. Reload the page and try again.");
            return;
        }
        onBusyChange?.(true);
        try {
            const response = await apiFetch(endpoint, { method: "DELETE" });
            onSettingsChange?.({
                logo_url: response?.settings?.logo_url || response?.logo_url || "",
            });
            onSuccess?.("Company logo removed.");
        } catch (error) {
            onError?.(error?.message || "Failed to remove the company logo.");
        } finally {
            onBusyChange?.(false);
        }
    };

    return (
        <div className="invoice-logo-uploader">
            <Upload.Dragger
                accept=".jpg,.jpeg,.png,.webp"
                disabled={disabled || !orgId}
                maxCount={1}
                multiple={false}
                showUploadList={false}
                beforeUpload={handleBeforeUpload}
                customRequest={handleUpload}
            >
                <p className="ant-upload-drag-icon">
                    {disabled ? <LoadingOutlined /> : <InboxOutlined />}
                </p>
                <p className="invoice-logo-uploader__title">
                    Drag your company logo here, or click to upload
                </p>
                <p className="invoice-logo-uploader__hint">
                    PNG, JPG, or WEBP up to 2 MB. This logo appears in company identity surfaces and is used as the invoice fallback when the invoice-settings logo is empty.
                </p>
            </Upload.Dragger>

            <div className="invoice-logo-preview-card">
                <div className="invoice-logo-preview-card__media">
                    {logoUrl ? (
                        <img src={logoUrl} alt="Current company logo" className="invoice-logo-preview-card__image" />
                    ) : (
                        <div className="invoice-logo-preview-card__empty">No company logo uploaded</div>
                    )}
                </div>
                <div className="invoice-logo-preview-card__content">
                    <h4>Current Company Logo</h4>
                    <p>
                        Invoice Settings can still override this for document rendering. Use this as the organization identity logo and invoice fallback.
                    </p>
                    <div className="invoice-logo-preview-card__actions">
                        <Button
                            type="default"
                            icon={<DeleteOutlined />}
                            onClick={handleRemove}
                            disabled={disabled || !orgId || !logoUrl}
                        >
                            Remove Logo
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
