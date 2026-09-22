import { useState, type ReactNode } from 'react'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Link from '@mui/material/Link'
import Typography from '@mui/material/Typography'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormProvider, useForm } from 'react-hook-form'
import { Link as RouterLink, Navigate } from 'react-router-dom'
import { z } from 'zod'

import { FormTextField } from '../../components/forms/FormTextField'
import { useAuth } from '../../features/auth/AuthContext'
import { roleHomePath } from '../../app/router'
import { getErrorMessage } from '../../utils/errors'
import { AuthShell } from './AuthShell'

const loginSchema = z.object({
  email: z.string().email('Enter a valid email address.'),
  password: z.string().min(1, 'Password is required.'),
})

type LoginFormValues = z.infer<typeof loginSchema>

export function LoginPage(): ReactNode {
  const { user, login } = useAuth()
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const methods = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  })

  if (user) {
    return <Navigate to={roleHomePath(user.roles)} replace />
  }

  const onSubmit = methods.handleSubmit(async (values) => {
    setSubmitting(true)
    setError(null)
    try {
      // login() populates the auth query cache; the existing `if (user)` redirect
      // above handles navigation to the role home once the context re-renders.
      await login(values.email, values.password)
    } catch (unknownError) {
      setError(getErrorMessage(unknownError, 'Unable to sign in. Please try again.'))
    } finally {
      setSubmitting(false)
    }
  })

  return (
    <AuthShell
      title="Sign in"
      subtitle="Access your member portal and cooperative services."
      footer={
        <>
          Don&apos;t have an account?{' '}
          <Link component={RouterLink} to="/register" sx={{ color: 'white', fontWeight: 700 }}>
            Register here
          </Link>
        </>
      }
    >
      <Box component="form" onSubmit={onSubmit} sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        {error ? <Alert severity="error">{error}</Alert> : null}

        <FormProvider {...methods}>
          <FormTextField<LoginFormValues> name="email" label="Email address" autoComplete="email" disabled={submitting} />
          <FormTextField<LoginFormValues> name="password" label="Password" type="password" autoComplete="current-password" disabled={submitting} />
        </FormProvider>

        <Button type="submit" variant="contained" size="large" disabled={submitting} sx={{ mt: 1 }}>
          {submitting ? 'Signing in…' : 'Sign in'}
        </Button>

        <Box component="p" sx={{ m: 0 }}>
          <Typography variant="caption" color="text.secondary">
            Accounts are activated after your membership application is reviewed and approved by the cooperative&apos;s
            administration.
          </Typography>
        </Box>
      </Box>
    </AuthShell>
  )
}